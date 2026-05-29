# Self-hosted runner (VM Oracle ARM64)

Pasos resumidos (detalle en Fase 6 del plan de implementación):
1. En GitHub: repo (privado) → Settings → Actions → Runners → New self-hosted runner → Linux ARM64. Copiar el token de registro.
2. En la VM (ubuntu@149.130.183.24), en `~/actions-runner`: descargar el runner ARM64, `./config.sh --url https://github.com/<usuario>/pinkmonkey-store --token <TOKEN> --labels ARM64 --unattended`.
3. Instalar como servicio: `sudo ./svc.sh install && sudo ./svc.sh start` (sobrevive reinicios).
4. El workflow `deploy.yml` corre con `runs-on: [self-hosted, ARM64]`.
5. Caddy: añadir `deploy/caddy/pinkmonkeyec.caddy` a la config de Caddy y recargar.

Tras cada despliegue el workflow corrige permisos de storage (www-data) y compila assets.
