import pytest
import requests


@pytest.fixture(scope="session")
def base_url() -> str:
    return "http://localhost:8080"


@pytest.fixture(scope="session")
def vuln_host() -> dict:
    return {"Host": "vuln.bank.local"}


@pytest.fixture(scope="session")
def secure_host() -> dict:
    return {"Host": "secure.bank.local"}


@pytest.fixture
def session(base_url):
    s = requests.Session()
    s.headers["User-Agent"] = "P03-Tests/1.0"
    yield s
    s.close()
