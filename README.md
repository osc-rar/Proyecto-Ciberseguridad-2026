# Proyecto-Ciberseguridad-2026

Repositorio de Proyecto de Ciberseguridad UCAB 2026.

Aplicacion web intencionalmente vulnerable que expone de forma didactica
riesgos del **OWASP API Security Top 10 (2023)**, especificamente:

- **API7:2023** - Server Side Request Forgery (SSRF)
- **API10:2023** - Consumo No Seguro de APIs (Unsafe Consumption of APIs)

> **ADVERTENCIA:** Esta aplicacion es vulnerable por diseno. Usar exclusivamente
> en entornos controlados y aislados (VMs de laboratorio).

## Estructura del Proyecto

```
├── api/api_mock.py              # API de terceros simulada (Python, puerto 5000)
├── backend/
│   ├── crear_perfil.php         # Endpoint SSRF (file_get_contents sin validacion)
│   └── ver_portafolio.php       # Consumo inseguro de API externa
├── frontend/
│   ├── index.html               # UI del portafolio
│   ├── script.js                # Renderizado con innerHTML (XSS)
│   └── styles.css
├── data/usuarios.txt            # Almacenamiento de perfiles
├── router.php                   # Router para PHP built-in server
└── docs/                        # Documentacion y guias de demo
```

## Inicio Rapido (Ubuntu Server)

```bash
# Terminal 1: API Mock
python3 api/api_mock.py 5000

# Terminal 2: Aplicacion PHP
php -S 0.0.0.0:8080 router.php
```

Acceder a `http://localhost:8080/` en el navegador.

## Documentacion de Demo (Fase 6)

| Documento | Contenido |
|---|---|
| [docs/README_demo.md](docs/README_demo.md) | Indice general, mapeo OWASP, orden de ejecucion |
| [docs/despliegue_vms.md](docs/despliegue_vms.md) | Guia de despliegue en VMs Kali + Ubuntu |
| [docs/demo_ssrf.md](docs/demo_ssrf.md) | Guion de ataque SSRF paso a paso |
| [docs/demo_xss_api.md](docs/demo_xss_api.md) | Guion de XSS via API comprometida |
| [docs/payloads.txt](docs/payloads.txt) | Catalogo de payloads de ataque |
| [docs/plan_app_vulnerable.md](docs/plan_app_vulnerable.md) | Plan de desarrollo completo (Fases 1-6) |

## Ramas

| Rama | Descripcion |
|---|---|
| `versión-vulnerable` | Implementacion con vulnerabilidades intencionales |
| `versión-asegurada` | Implementacion con mitigaciones aplicadas |
| `main` | Rama principal del repositorio |

## Mapeo de Vulnerabilidades

| Vulnerabilidad | OWASP | CWE | Archivo |
|---|---|---|---|
| SSRF | API7:2023 | CWE-918 | `backend/crear_perfil.php` |
| Consumo No Seguro de APIs | API10:2023 | CWE-20 | `backend/ver_portafolio.php` |
| Cross-Site Scripting | — | CWE-79 | `frontend/script.js` |
