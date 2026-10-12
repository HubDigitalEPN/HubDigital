package org.hubdigital.signatures;

import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpServer;
import java.net.InetSocketAddress;
import java.net.URI;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.security.KeyStore;
import java.security.PrivateKey;
import java.security.Provider;
import java.security.SecureRandom;
import java.security.Security;
import java.security.cert.X509Certificate;
import java.time.Instant;
import java.util.Arrays;
import java.util.Base64;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.Executors;
import org.bouncycastle.jce.provider.BouncyCastleProvider;

/** Conector del equipo del firmante. La clave privada permanece dentro del token. */
public final class TokenUsbBridge {
    private record Session(String origin, Instant expires) {}
    private final Map<String, Session> sessions = new ConcurrentHashMap<>();
    private final Provider provider;
    private final String origin;
    private static final int MAX_PDF = 16 * 1024 * 1024;

    private TokenUsbBridge(Provider provider, String origin) { this.provider = provider; this.origin = origin; }

    public static void main(String[] args) throws Exception {
        if (args.length != 2) throw new IllegalArgumentException("Uso: TokenUsbBridge CONFIG_PKCS11 ORIGEN_HUBDIGITAL");
        URI origin = URI.create(args[1]);
        boolean local = "http".equals(origin.getScheme()) && java.util.Set.of("localhost", "127.0.0.1").contains(origin.getHost());
        if ((!"https".equals(origin.getScheme()) && !local) || origin.getUserInfo() != null
            || origin.getHost() == null || origin.getRawQuery() != null || origin.getFragment() != null
            || (origin.getPath() != null && !origin.getPath().isEmpty())) {
            throw new IllegalArgumentException("Indica el origen exacto HTTPS del portal, sin ruta; HTTP solo se admite en loopback.");
        }
        Security.addProvider(new BouncyCastleProvider());
        Provider provider = Security.getProvider("SunPKCS11").configure(Path.of(args[0]).toAbsolutePath().toString());
        Security.addProvider(provider);
        TokenUsbBridge bridge = new TokenUsbBridge(provider, args[1]);
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", 17654), 4);
        server.createContext("/", bridge::handle);
        server.setExecutor(Executors.newSingleThreadExecutor());
        server.start();
        System.out.println("Conector HubDigital USB listo en 127.0.0.1:17654 para " + args[1]);
    }

    private void handle(HttpExchange exchange) throws java.io.IOException {
        char[] pin = null;
        Path temp = null;
        KeyStore store = null;
        try {
            if (!origin.equals(exchange.getRequestHeaders().getFirst("Origin"))
                || !"127.0.0.1:17654".equals(exchange.getRequestHeaders().getFirst("Host"))) {
                error(exchange, 403, "Origen no autorizado."); return;
            }
            var headers = exchange.getResponseHeaders();
            headers.set("Access-Control-Allow-Origin", origin);
            headers.set("Vary", "Origin");
            headers.set("Cache-Control", "no-store");
            headers.set("X-Content-Type-Options", "nosniff");
            if ("OPTIONS".equals(exchange.getRequestMethod())) {
                headers.set("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
                headers.set("Access-Control-Allow-Headers", "Content-Type, X-HubDigital-Bridge, X-Bridge-Session, X-Token-Pin, X-Token-Alias");
                headers.set("Access-Control-Allow-Private-Network", "true");
                exchange.sendResponseHeaders(204, -1); return;
            }
            if (!"1".equals(exchange.getRequestHeaders().getFirst("X-HubDigital-Bridge"))) {
                error(exchange, 403, "Petición del conector no autorizada."); return;
            }
            String path = exchange.getRequestURI().getPath();
            sessions.entrySet().removeIf(entry -> entry.getValue().expires.isBefore(Instant.now()));
            if ("/session".equals(path) && "GET".equals(exchange.getRequestMethod())) {
                if (sessions.size() >= 32) { error(exchange, 429, "Demasiadas sesiones locales."); return; }
                byte[] random = new byte[32]; new SecureRandom().nextBytes(random);
                String token = Base64.getUrlEncoder().withoutPadding().encodeToString(random);
                sessions.put(token, new Session(origin, Instant.now().plusSeconds(120)));
                json(exchange, 200, "{\"token\":\"" + token + "\"}"); return;
            }
            String token = exchange.getRequestHeaders().getFirst("X-Bridge-Session");
            Session session = token == null ? null : sessions.get(token);
            if (session == null || !session.origin.equals(origin) || !"POST".equals(exchange.getRequestMethod())) {
                error(exchange, 403, "La sesión del conector expiró. Vuelve a firmar."); return;
            }
            if (!java.util.Set.of("/certificates", "/sign").contains(path)) {
                error(exchange, 404, "Operación desconocida."); return;
            }
            String rawPin = exchange.getRequestHeaders().getFirst("X-Token-Pin");
            if (rawPin == null || rawPin.isEmpty() || rawPin.length() > 128 || rawPin.indexOf('\0') >= 0) {
                error(exchange, 422, "Ingresa el PIN del token."); return;
            }
            pin = rawPin.toCharArray();
            store = KeyStore.getInstance("PKCS11", provider);
            store.load(null, pin);
            if ("/certificates".equals(path)) {
                StringBuilder list = new StringBuilder();
                var aliases = store.aliases();
                while (aliases.hasMoreElements()) {
                    String alias = aliases.nextElement();
                    if (!store.isKeyEntry(alias) || !(store.getCertificate(alias) instanceof X509Certificate cert)) continue;
                    if (!list.isEmpty()) list.append(',');
                    list.append("{\"alias\":\"").append(escape(alias)).append("\",\"nombre\":\"")
                        .append(escape(cert.getSubjectX500Principal().getName())).append("\"}");
                }
                if (list.isEmpty()) { error(exchange, 422, "El token no contiene un certificado con clave de firma."); return; }
                json(exchange, 200, "{\"certificados\":[" + list + "]}"); return;
            }
            sessions.remove(token);
            if (!"application/pdf".equals(exchange.getRequestHeaders().getFirst("Content-Type"))) {
                error(exchange, 415, "El original debe ser un PDF."); return;
            }
            String aliasHeader = exchange.getRequestHeaders().getFirst("X-Token-Alias");
            String alias = aliasHeader == null ? "" : URLDecoder.decode(aliasHeader, StandardCharsets.UTF_8);
            if (!store.isKeyEntry(alias)) { error(exchange, 422, "Selecciona un certificado del token."); return; }
            byte[] pdf = exchange.getRequestBody().readNBytes(MAX_PDF + 1);
            if (pdf.length > MAX_PDF) { error(exchange, 413, "El PDF supera el tamaño permitido."); return; }
            temp = Files.createTempDirectory("hubdigital-token-");
            Path original = temp.resolve("original.pdf"), signed = temp.resolve("firmado.pdf");
            Files.write(original, pdf);
            if (!PdfSignatureCli.originalSeguro(original)) { error(exchange, 422, "El PDF original no cumple el perfil seguro."); return; }
            FirmadorPdfOficial.firmarConIdentidad(original, signed, (PrivateKey) store.getKey(alias, null),
                store.getCertificateChain(alias), "solicitud-deposito:depositante:v1", provider.getName());
            byte[] output = Files.readAllBytes(signed);
            headers.set("Content-Type", "application/pdf");
            exchange.sendResponseHeaders(200, output.length);
            exchange.getResponseBody().write(output);
        } catch (java.security.cert.CertificateExpiredException e) {
            error(exchange, 422, "El certificado del token está caducado.");
        } catch (java.security.cert.CertificateNotYetValidException e) {
            error(exchange, 422, "El certificado del token aún no está vigente.");
        } catch (Exception e) {
            // No se devuelven excepciones del controlador ni credenciales.
            error(exchange, 422, "No se pudo acceder al token. Comprueba el PIN, la conexión USB y el controlador del fabricante.");
        } finally {
            if (pin != null) Arrays.fill(pin, '\0');
            if (provider instanceof java.security.AuthProvider auth) {
                try { auth.logout(); } catch (Exception ignored) { }
            }
            if (temp != null) {
                Files.deleteIfExists(temp.resolve("original.pdf")); Files.deleteIfExists(temp.resolve("firmado.pdf"));
                Files.deleteIfExists(temp);
            }
            exchange.close();
        }
    }

    private static void error(HttpExchange exchange, int status, String message) throws java.io.IOException {
        json(exchange, status, "{\"message\":\"" + escape(message) + "\"}");
    }
    private static void json(HttpExchange exchange, int status, String body) throws java.io.IOException {
        byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().set("Content-Type", "application/json; charset=utf-8");
        exchange.sendResponseHeaders(status, bytes.length); exchange.getResponseBody().write(bytes);
    }
    private static String escape(String text) {
        StringBuilder result = new StringBuilder();
        for (char c : text.toCharArray()) {
            if (c == '\\' || c == '"') result.append('\\').append(c);
            else if (c < 32 || c > 126) result.append(String.format("\\u%04x", (int) c));
            else result.append(c);
        }
        return result.toString();
    }
}
