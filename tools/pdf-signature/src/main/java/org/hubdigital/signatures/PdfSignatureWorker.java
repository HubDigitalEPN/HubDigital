package org.hubdigital.signatures;

import java.net.StandardProtocolFamily;
import java.net.UnixDomainSocketAddress;
import java.nio.ByteBuffer;
import java.nio.channels.SelectionKey;
import java.nio.channels.Selector;
import java.nio.channels.ServerSocketChannel;
import java.nio.channels.SocketChannel;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.LinkOption;
import java.nio.file.Path;
import java.nio.file.attribute.PosixFilePermissions;
import java.util.Arrays;
import java.util.concurrent.ArrayBlockingQueue;
import java.util.concurrent.ThreadPoolExecutor;
import java.util.concurrent.TimeUnit;

/** JVM preparada, accesible sólo por socket Unix privado, sin claves persistentes. */
final class PdfSignatureWorker {
    private static final int MAX_FRAME = 65_536;
    private PdfSignatureWorker() {}

    static void serve(Path socket) throws Exception {
        Path jar = Path.of(PdfSignatureCli.class.getProtectionDomain().getCodeSource().getLocation().toURI()).toRealPath();
        String identity = PdfSignatureCli.sha256(jar);
        Path root = Path.of(System.getProperty("java.io.tmpdir")).toRealPath();
        String trust = Path.of(System.getenv("HUBDIGITAL_SIGNATURE_TRUST_DIR")).toRealPath().toString();
        String limits = Integer.getInteger("hubdigital.pdf.max_pages", 40) + ":"
            + Integer.getInteger("hubdigital.pdf.max_page_points", 1440) + ":"
            + Integer.getInteger("hubdigital.pdf.render_dpi", 110) + ":"
            + Long.getLong("hubdigital.pdf.max_render_pixels", 100_000_000L);
        PdfSignatureCli.prepareEngine();
        Files.deleteIfExists(socket);
        ThreadPoolExecutor jobs = new ThreadPoolExecutor(1, 1, 0, TimeUnit.SECONDS,
            new ArrayBlockingQueue<>(8));
        try (ServerSocketChannel server = ServerSocketChannel.open(StandardProtocolFamily.UNIX)) {
            server.bind(UnixDomainSocketAddress.of(socket));
            Files.setPosixFilePermissions(socket, PosixFilePermissions.fromString("rw-------"));
            while (true) {
                SocketChannel peer = server.accept();
                try {
                    jobs.execute(() -> handle(peer, identity, root, trust, limits));
                } catch (java.util.concurrent.RejectedExecutionException full) {
                    peer.close();
                }
            }
        } finally {
            jobs.shutdownNow();
            Files.deleteIfExists(socket);
        }
    }

    private static void handle(SocketChannel peer, String identity, Path root, String trust, String limits) {
        byte[] input = null, request = null;
        String[] fields = null;
        try (peer; Selector selector = Selector.open()) {
            peer.configureBlocking(false);
            peer.register(selector, SelectionKey.OP_READ);
            ByteBuffer header = ByteBuffer.allocate(4);
            transfer(peer, selector, header, false);
            int size = header.flip().getInt();
            if (size < 1 || size > MAX_FRAME) throw new IllegalArgumentException("Tamaño de petición inválido.");
            input = new byte[size];
            transfer(peer, selector, ByteBuffer.wrap(input), false);
            String result;
            if (Arrays.equals(input, "health".getBytes(StandardCharsets.US_ASCII))) {
                result = "{\"status\":\"ready\",\"jar_sha256\":\"" + identity + "\"}";
            } else {
                fields = new String(input, StandardCharsets.UTF_8).split("\\x00", -1);
                if (fields.length != 8 || !identity.equals(fields[0]) || !trust.equals(fields[1]) || !limits.equals(fields[2])) {
                    result = "{\"status\":\"worker_incompatible\"}";
                } else {
                    validatePath(root, fields[3], false, 16L * 1024 * 1024);
                    boolean verify = "verify-only".equals(fields[7]);
                    validatePath(root, fields[4], !verify, 16L * 1024 * 1024);
                    if (!verify) validatePath(root, fields[5], false, 5L * 1024 * 1024);
                    request = String.join("\0", Arrays.copyOfRange(fields, 3, 8)).getBytes(StandardCharsets.UTF_8);
                    try { result = verify ? PdfSignatureCli.officialResult(Path.of(fields[4]), Path.of(fields[3]))
                        : PdfSignatureCli.signRequest(request, true); }
                    catch (Exception error) { result = PdfSignatureCli.errorResult(error); }
                }
            }
            byte[] response = result.getBytes(StandardCharsets.UTF_8);
            if (response.length > MAX_FRAME) throw new IllegalStateException("Dictamen demasiado largo.");
            ByteBuffer output = ByteBuffer.allocate(4 + response.length).putInt(response.length).put(response);
            output.flip();
            peer.keyFor(selector).interestOps(SelectionKey.OP_WRITE);
            transfer(peer, selector, output, true);
        } catch (Exception ignored) {
            // No se registran entradas, rutas, contraseñas ni datos del certificado.
        } finally {
            if (input != null) Arrays.fill(input, (byte) 0);
            if (request != null) Arrays.fill(request, (byte) 0);
            if (fields != null) Arrays.fill(fields, "");
        }
    }

    static void validatePath(Path root, String value, boolean output, long max) throws Exception {
        Path path = Path.of(value).toAbsolutePath().normalize();
        if (!path.startsWith(root) || path.equals(root) || Files.isSymbolicLink(path)
            || !path.getParent().toRealPath().startsWith(root)) {
            throw new IllegalArgumentException("Ruta documental fuera del directorio privado.");
        }
        if (output) {
            if (Files.exists(path, LinkOption.NOFOLLOW_LINKS)) throw new IllegalArgumentException("La salida ya existe.");
        } else if (!Files.isRegularFile(path, LinkOption.NOFOLLOW_LINKS) || !path.toRealPath().startsWith(root)
            || Files.size(path) > max) {
            throw new IllegalArgumentException("Entrada documental inválida.");
        }
    }

    private static void transfer(SocketChannel peer, Selector selector, ByteBuffer buffer, boolean write) throws Exception {
        long deadline = System.nanoTime() + TimeUnit.SECONDS.toNanos(5);
        while (buffer.hasRemaining()) {
            int count = write ? peer.write(buffer) : peer.read(buffer);
            if (count < 0) throw new java.io.EOFException();
            if (count == 0) {
                long left = deadline - System.nanoTime();
                if (left <= 0) throw new java.io.IOException("Transporte documental agotado.");
                selector.select(Math.max(1, TimeUnit.NANOSECONDS.toMillis(left)));
                selector.selectedKeys().clear();
            }
        }
    }
}
