# Tests automatisés

```bash
pip install -r ../scripts/requirements.txt
pytest -v
```

- `test_vulnerable.py` — prouve que l'attaque RÉUSSIT sur l'app vulnérable.
- `test_secure.py`     — prouve que l'attaque ÉCHOUE sur l'app sécurisée.

Pré-requis : `docker compose up` doit tourner et `/etc/hosts` doit contenir
les entrées `vuln.bank.local` et `secure.bank.local` pointant sur 127.0.0.1.
