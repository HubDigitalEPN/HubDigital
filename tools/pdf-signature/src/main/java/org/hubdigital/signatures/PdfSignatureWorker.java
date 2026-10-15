package org.hubdigital.signatures;

import java.net.StandardProtocolFamily;
import java.net.UnixDomainSocketAddress;
import java.net.InetSocketAddress;
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
    private static final int MAX_RESPONSE = 1_048_576;
    private record Engine(String identity, Path root, String trust, String limits) {}
    private PdfSignatureWorker() {}

    private static Engine prepare() throws Exception {
        Path jar = Path.of(PdfSignatureCli.class.getProtectionDomain().getCodeSource().getLocation().toURI()).toRealPath();
        String identity = PdfSignatureCli.sha256(jar);
        Path root = Path.of(System.getProperty("java.io.tmpdir")).toRealPath();
        String trust = Path.of(System.getenv("HUBDIGITAL_SIGNATURE_TRUST_DIR")).toRealPath().toString();
        String limits = Integer.getInteger("hubdigital.pdf.max_pages", 40) + ":"
            + Integer.getInteger("hubdigital.pdf.max_page_points", 1440) + ":"
            + Integer.getInteger("hubdigital.pdf.render_dpi", 110) + ":"
            + Long.getLong("hubdigital.pdf.max_render_pixels", 100_000_000L);
        PdfSignatureCli.prepareEngine();
        PdfSignatureCli.preparePublicRevocation();
        return new Engine(identity, root, trust, limits);
    }

    static void serve(Path socket) throws Exception {
        Engine engine = prepare();
        Files.deleteIfExists(socket);
        try (ServerSocketChannel server = ServerSocketChannel.open(StandardProtocolFamily.UNIX)) {
            server.bind(UnixDomainSocketAddress.of(socket));
            Files.setPosixFilePermissions(socket, PosixFilePermissions.fromString("rw-------"));
            serveConnections(server, engine, null);
        } finally {
            Files.deleteIfExists(socket);
        }
    }

    /** Desarrollo Windows: loopback fijo y autenticación mutua antes de recibir credenciales. */
    static void serveLocal(Path keyFile) throws Exception {
        if (!System.getProperty("os.name", "").startsWith("Windows")) {
            throw new IllegalArgumentException("El transporte local está reservado a Windows.");
        }
        if (Files.isSymbolicLink(keyFile) || Files.size(keyFile) != 32) throw new IllegalArgumentException("Clave local inválida.");
        byte[] key = Files.readAllBytes(keyFile);
        try (ServerSocketChannel server = ServerSocketChannel.open(StandardProtocolFamily.INET)) {
            server.bind(new InetSocketAddress("127.0.0.1", 17655), 8);
            Engine engine = prepare();
            System.out.println("LOCAL_SIGNATURE_READY"); System.out.flush();
            serveConnections(server, engine, key);
        } finally {
            Arrays.fill(key, (byte) 0);
        }
    }

    private static void serveConnections(ServerSocketChannel server, Engine engine, byte[] key) throws Exception {
        ThreadPoolExecutor jobs = new ThreadPoolExecutor(1, 1, 0, TimeUnit.SECONDS, new ArrayBlockingQueue<>(8));
        try {
            while (true) {
                SocketChannel peer = server.accept();
                try {
                    if (peer.getLocalAddress() instanceof InetSocketAddress) peer.setOption(java.net.StandardSocketOptions.TCP_NODELAY, true);
                    jobs.execute(() -> handle(peer, engine.identity, engine.root, engine.trust, engine.limits, key));
                } catch (java.util.concurrent.RejectedExecutionException full) {
                    peer.close();
                }
            }
        } finally {
            jobs.shutdownNow();
        }
    }

    private static void handle(SocketChannel peer, String identity, Path root, String trust, String limits, byte[] key) {
        byte[] input = null, request = null;
        String[] fields = null;
        try (peer; Selector selector = Selector.open()) {
            peer.configureBlocking(false);
            peer.register(selector, SelectionKey.OP_READ);
            if (key != null) {
                byte[] hello = readFrame(peer, selector);
                String[] proof;
                try { proof = new String(hello, StandardCharsets.US_ASCII).split(":", -1); }
                finally { Arrays.fill(hello, (byte) 0); }
                if (proof.length != 3 || !"hello".equals(proof[0]) || !proof[1].matches("[0-9a-f]{64}")
                    || !proof[2].matches("[0-9a-f]{64}") || !java.security.MessageDigest.isEqual(
                        mac(key, proof[1]).getBytes(StandardCharsets.US_ASCII), proof[2].getBytes(StandardCharsets.US_ASCII))) return;
                writeFrame(peer, selector, "{\"status\":\"ready\",\"jar_sha256\":\"" + identity
                    + "\",\"operations\":[\"verify-external\",\"inspect-external\",\"read-text\"],\"proof\":\"" + mac(key, proof[1] + "\0" + identity) + "\"}");
            }
            input = readFrame(peer, selector);
            if (Arrays.equals(input, "health".getBytes(StandardCharsets.US_ASCII))) {
                writeFrame(peer, selector, "{\"status\":\"ready\",\"jar_sha256\":\"" + identity + "\",\"operations\":[\"verify-external\",\"inspect-external\",\"read-text\"]}");
                Arrays.fill(input, (byte) 0);
                input = readFrame(peer, selector);
            }
            String result;
            {
                fields = new String(input, StandardCharsets.UTF_8).split("\\x00", -1);
                if (fields.length != 8 || !identity.equals(fields[0]) || !trust.equals(fields[1]) || !limits.equals(fields[2])) {
                    result = "{\"status\":\"worker_incompatible\"}";
                } else {
                    boolean external = "verify-external".equals(fields[7]);
                    boolean inspect = "inspect-external".equals(fields[7]);
                    boolean text = "read-text".equals(fields[7]);
                    boolean inputOnly = external || inspect || text;
                    validatePath(root, fields[3], false, (inputOnly ? 20L : 16L) * 1024 * 1024);
                    boolean prepare = "prepare-only".equals(fields[7]);
                    boolean verify = "verify-only".equals(fields[7]);
                    if (!prepare && !inputOnly) {
                        validatePath(root, fields[4], !verify, 16L * 1024 * 1024);
                        if (!verify) validatePath(root, fields[5], false, 5L * 1024 * 1024);
                        request = String.join("\0", Arrays.copyOfRange(fields, 3, 8)).getBytes(StandardCharsets.UTF_8);
                    } else if (!fields[4].isEmpty() || !fields[5].isEmpty() || !fields[6].isEmpty()) {
                        throw new IllegalArgumentException("La preparación no admite credenciales ni una salida.");
                    }
                    try { result = prepare ? "{\"status\":\"" + (ContenidoOficialPdf.preparar(Path.of(fields[3])) ? "prepared" : "not_prepared") + "\"}"
                        : external ? PdfSignatureCli.externalResult(Path.of(fields[3]))
                        : inspect ? PdfSignatureCli.inspectResult(Path.of(fields[3]))
                        : text ? PdfSignatureCli.textResult(Path.of(fields[3]))
                        : verify ? PdfSignatureCli.officialResult(Path.of(fields[4]), Path.of(fields[3]))
                        : PdfSignatureCli.signRequest(request, true); }
                    catch (Exception error) { result = PdfSignatureCli.errorResult(error); }
                }
            }
            writeFrame(peer, selector, result);
        } catch (Exception ignored) {
            // No se registran entradas, rutas, contraseñas ni datos del certificado.
        } finally {
            if (input != null) Arrays.fill(input, (byte) 0);
            if (request != null) Arrays.fill(request, (byte) 0);
            if (fields != null) Arrays.fill(fields, "");
        }
    }

    private static byte[] readFrame(SocketChannel peer, Selector selector) throws Exception {
        peer.keyFor(selector).interestOps(SelectionKey.OP_READ);
        ByteBuffer header = ByteBuffer.allocate(4);
        transfer(peer, selector, header, false);
        int size = header.flip().getInt();
        if (size < 1 || size > MAX_FRAME) throw new IllegalArgumentException("Tamaño de petición inválido.");
        byte[] bytes = new byte[size];
        transfer(peer, selector, ByteBuffer.wrap(bytes), false);
        return bytes;
    }

    private static void writeFrame(SocketChannel peer, Selector selector, String value) throws Exception {
        byte[] bytes = value.getBytes(StandardCharsets.UTF_8);
        if (bytes.length > MAX_RESPONSE) throw new IllegalStateException("Dictamen demasiado largo.");
        ByteBuffer output = ByteBuffer.allocate(4 + bytes.length).putInt(bytes.length).put(bytes);
        output.flip();
        peer.keyFor(selector).interestOps(SelectionKey.OP_WRITE);
        transfer(peer, selector, output, true);
    }

    private static String mac(byte[] key, String value) throws Exception {
        javax.crypto.Mac mac = javax.crypto.Mac.getInstance("HmacSHA256");
        mac.init(new javax.crypto.spec.SecretKeySpec(key, "HmacSHA256"));
        return java.util.HexFormat.of().formatHex(mac.doFinal(value.getBytes(StandardCharsets.UTF_8)));
    }

    /** El paquete comprueba la conversación real y el rechazo previo a cualquier operación documental. */
    static void selftestLocalAuthentication() throws Exception {
        byte[] key = new byte[32]; new java.security.SecureRandom().nextBytes(key);
        String identity = "a".repeat(64), nonce = "b".repeat(64);
        try (ServerSocketChannel server = ServerSocketChannel.open(StandardProtocolFamily.INET)) {
            server.bind(new InetSocketAddress("127.0.0.1", 0));
            for (String operation : new String[]{"rejected", "verify-external", "inspect-external", "read-text"}) {
                boolean valid = !"rejected".equals(operation);
                try (SocketChannel client = SocketChannel.open(server.getLocalAddress())) {
                    SocketChannel peer = server.accept();
                    Thread handler = new Thread(() -> handle(peer, identity, Path.of(System.getProperty("java.io.tmpdir")), "", "", key));
                    handler.start();
                    try (Selector selector = Selector.open()) {
                        client.configureBlocking(false); client.register(selector, SelectionKey.OP_WRITE);
                        writeFrame(client, selector, "hello:" + nonce + ":" + (valid ? mac(key, nonce) : "0".repeat(64)));
                        if (!valid) {
                            boolean rejected = false;
                            try { readFrame(client, selector); } catch (java.io.EOFException expected) { rejected = true; }
                            if (!rejected) throw new IllegalStateException("El motor local aceptó un cliente sin clave.");
                        } else {
                            String response = new String(readFrame(client, selector), StandardCharsets.UTF_8);
                            if (!response.contains("\"proof\":\"" + mac(key, nonce + "\0" + identity) + "\"")) {
                                throw new IllegalStateException("El motor local no acreditó su identidad.");
                            }
                            writeFrame(client, selector, "health");
                            if (!new String(readFrame(client, selector), StandardCharsets.UTF_8).contains(identity)) {
                                throw new IllegalStateException("El motor local perdió el encuadre después de autenticar.");
                            }
                            Path sample = Files.createTempFile("verificacion-externa-", ".pdf");
                            try {
                                try (org.apache.pdfbox.pdmodel.PDDocument pdf = new org.apache.pdfbox.pdmodel.PDDocument()) {
                                    pdf.addPage(new org.apache.pdfbox.pdmodel.PDPage()); pdf.save(sample.toFile());
                                }
                                writeFrame(client, selector, String.join("\0", identity, "", "", sample.toString(), "", "", "", operation));
                                String result = new String(readFrame(client, selector), StandardCharsets.UTF_8);
                                if ("verify-external".equals(operation) && !result.contains("\"status\":\"sin_firma\"")) {
                                    throw new IllegalStateException("La verificación externa preparada no conservó el dictamen sin firma.");
                                }
                                if ("inspect-external".equals(operation) && (!result.contains("\"status\":\"seguro\"") || !result.contains("\"paginas\":1"))) {
                                    throw new IllegalStateException("El inspector preparado no conservó la estructura comprobada.");
                                }
                                if ("read-text".equals(operation) && (!result.contains("\"numero\":1") || !result.contains("\"texto\":\"\""))) {
                                    throw new IllegalStateException("El lector preparado no conservó las páginas del PDF.");
                                }
                            } finally { Files.deleteIfExists(sample); }
                        }
                    } finally { client.close(); handler.join(6000); }
                    if (handler.isAlive()) throw new IllegalStateException("El transporte local no cerró la petición.");
                }
            }
        } finally { Arrays.fill(key, (byte) 0); }
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
