<?php
// app-secure/src/security.php — utilitaires sécurité partagés

/**
 * Échappement contextuel HTML (par défaut).
 * Toujours préférer e() à un echo brut sur une donnée utilisateur.
 */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Empreinte de la requête : UA + classe réseau /24. Permet de détecter un détournement. */
function request_fingerprint(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // On normalise l'IP au /24 pour éviter les faux positifs (mobile/load balancer)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $ip    = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
    }
    return hash('sha256', $ip . '|' . $ua);
}

/** Token CSRF par session, comparé en temps constant. */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function csrf_check(): bool {
    $given = $_POST['_csrf'] ?? '';
    return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $given);
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Headers de sécurité — appliqués sur chaque réponse HTML.
 * Mettre dans un middleware en production.
 */
function security_headers(): void {
    if (headers_sent()) return;
    // CSP stricte : pas de inline script, pas de eval, pas de data:, pas de iframe externe
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "img-src 'self' data:; "
         . "object-src 'none'; "
         . "base-uri 'self'; "
         . "form-action 'self'; "
         . "frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');  // activé en prod HTTPS
}
