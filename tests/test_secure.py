"""
Tests sur l'application SÉCURISÉE.
Ces tests doivent PASSER → ils prouvent que les contre-mesures fonctionnent.
"""
import re
import requests

ATTACKER_SID = "ATTACKER_FIXED_SID_TEST_002"


def _cookie_name():
    return "SBSID"  # défini dans php.ini de la version sécurisée


def test_rejects_arbitrary_session_id(base_url, secure_host):
    """strict_mode rejette tout SID non émis par le serveur."""
    r = requests.get(f"{base_url}/login.php",
                     headers=secure_host,
                     cookies={_cookie_name(): ATTACKER_SID})
    set_cookie = r.headers.get("Set-Cookie", "")
    assert ATTACKER_SID not in set_cookie, "le SID forgé ne doit jamais être ré-émis"


def test_regenerates_session_id_on_login(base_url, secure_host):
    """Après login, le SID DOIT être différent du SID pré-auth."""
    s = requests.Session()
    s.headers.update(secure_host)
    s.get(f"{base_url}/login.php")  # acquérir un SID légitime

    pre_login_sid = s.cookies.get(_cookie_name())
    assert pre_login_sid, "un SID doit être attribué au premier appel"

    # récupérer csrf
    r0 = s.get(f"{base_url}/login.php")
    m = re.search(r'name="_csrf" value="([a-f0-9]{64})"', r0.text)
    assert m, "le formulaire de login doit contenir un token CSRF"
    csrf = m.group(1)

    r = s.post(f"{base_url}/login.php",
               data={"username": "alice", "password": "Password123!", "_csrf": csrf},
               allow_redirects=False)
    assert r.status_code in (302, 303), "login d'Alice doit aboutir"

    post_login_sid = s.cookies.get(_cookie_name())
    assert post_login_sid != pre_login_sid, (
        "Le SID doit être régénéré après authentification — sinon vulnérable à la fixation"
    )


def test_attacker_cannot_hijack(base_url, secure_host):
    """
    Mallory pose un SID forgé, Alice se loggue ; Mallory rejoue ce SID
    et doit retomber sur la page de login.
    """
    # Alice obtient une session propre via son navigateur
    alice = requests.Session()
    alice.headers.update(secure_host)
    r0 = alice.get(f"{base_url}/login.php")
    csrf = re.search(r'name="_csrf" value="([a-f0-9]{64})"', r0.text).group(1)
    alice.post(f"{base_url}/login.php",
               data={"username": "alice", "password": "Password123!", "_csrf": csrf},
               allow_redirects=False)

    # Mallory essaie d'utiliser le SID arbitraire fixé en URL
    r = requests.get(f"{base_url}/dashboard.php",
                     headers=secure_host,
                     cookies={_cookie_name(): ATTACKER_SID},
                     allow_redirects=False)
    assert r.status_code in (302, 303), "session forgée -> redirect vers login attendu"


def test_cookie_is_httponly_and_samesite_strict(base_url, secure_host):
    r = requests.get(f"{base_url}/login.php", headers=secure_host)
    set_cookie = r.headers.get("Set-Cookie", "")
    assert "HttpOnly" in set_cookie
    assert "SameSite=Strict" in set_cookie


def test_csp_header_present(base_url, secure_host):
    r = requests.get(f"{base_url}/", headers=secure_host)
    csp = r.headers.get("Content-Security-Policy", "")
    assert "default-src 'self'" in csp
    assert "object-src 'none'" in csp
    assert "script-src 'self'" in csp


def test_xss_payload_is_escaped_in_comments(base_url, secure_host):
    s = requests.Session()
    s.headers.update(secure_host)
    r0 = s.get(f"{base_url}/login.php")
    csrf = re.search(r'name="_csrf" value="([a-f0-9]{64})"', r0.text).group(1)
    s.post(f"{base_url}/login.php",
           data={"username": "mallory", "password": "EvilPass1!", "_csrf": csrf},
           allow_redirects=False)

    r0 = s.get(f"{base_url}/comments.php")
    csrf = re.search(r'name="_csrf" value="([a-f0-9]{64})"', r0.text).group(1)
    payload = '<script id="xss-marker">alert(1)</script>'
    s.post(f"{base_url}/comments.php", data={"content": payload, "_csrf": csrf})

    r = s.get(f"{base_url}/comments.php")
    assert payload not in r.text, "le payload brut ne doit JAMAIS apparaître"
    assert "&lt;script" in r.text, "le payload doit être HTML-encodé"


def test_csrf_required_on_transfer(base_url, secure_host):
    s = requests.Session()
    s.headers.update(secure_host)
    r0 = s.get(f"{base_url}/login.php")
    csrf = re.search(r'name="_csrf" value="([a-f0-9]{64})"', r0.text).group(1)
    s.post(f"{base_url}/login.php",
           data={"username": "alice", "password": "Password123!", "_csrf": csrf},
           allow_redirects=False)

    r = s.post(f"{base_url}/transfer.php",
               data={"to_iban": "FR7630001007940000000000042", "amount": "10",
                     "note": "no csrf"},
               allow_redirects=False)
    assert r.status_code == 400, "Une mutation sans CSRF token doit être refusée"
