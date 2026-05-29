# Manejo de secretos

- `.env` de producción vive SOLO en la VM (`~/pinkmonkey-store/.env`), nunca en git (está en .gitignore).
- Variables sensibles: APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, MAIL_*, datos bancarios de la transferencia.
- Cambiar las credenciales por defecto del admin (`admin@example.com`/`admin123`) antes de exponer la tienda.
- Opción de cifrar `.env` en el repo con sops+age:
    sops --encrypt --age <pubkey> .env > .env.enc   # .env.enc SÍ puede ir a git
    sops --decrypt .env.enc > .env                  # en la VM al desplegar
- Rotación de DB_PASSWORD: actualizar `.env` y `docker compose up -d db`.
