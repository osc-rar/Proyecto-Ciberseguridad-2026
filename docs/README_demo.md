# Indice de Demostracion - Fase 6

Proyecto de Ciberseguridad UCAB 2026. Aplicacion web intencionalmente
vulnerable que expone riesgos del **OWASP API Security Top 10 (2023)**.

> **Uso exclusivo en entorno controlado y aislado (VMs Kali + Ubuntu Server).**

## Entregables de la Fase 6

| # | Documento | Descripcion |
|---|---|---|
| 6.1 | [payloads.txt](payloads.txt) | Catalogo de payloads SSRF y XSS con comentarios |
| 6.2 | [despliegue_vms.md](despliegue_vms.md) | Guia de despliegue en VMs (Kali + Ubuntu Server) |
| 6.3 | [demo_ssrf.md](demo_ssrf.md) | Guion paso a paso del ataque SSRF |
| 6.4 | [demo_xss_api.md](demo_xss_api.md) | Guion paso a paso del XSS via API comprometida |

## Orden de Ejecucion Recomendado

```
1. Despliegue       -> Seguir despliegue_vms.md (checklist completo)
2. Demo SSRF        -> Seguir demo_ssrf.md (Pasos 1-4)
3. Reset            -> Reiniciar api_mock + vaciar usuarios.txt
4. Demo XSS         -> Seguir demo_xss_api.md (Pasos 1-4)
5. Reset final      -> Restaurar estado limpio
```

## Mapeo OWASP API Security Top 10 (2023)

| Vulnerabilidad | OWASP | CWE | Vector | Archivo | Demo |
|---|---|---|---|---|---|
| Server Side Request Forgery | **API7:2023** | CWE-918 | Campo `url_repositorio` en POST | `backend/crear_perfil.php` | [demo_ssrf.md](demo_ssrf.md) |
| Consumo No Seguro de APIs | **API10:2023** | CWE-20 | Confianza ciega en respuesta de API | `backend/ver_portafolio.php` | [demo_xss_api.md](demo_xss_api.md) |
| Cross-Site Scripting (consecuencia) | — | CWE-79 | `innerHTML` sin escape de datos de API | `frontend/script.js` | [demo_xss_api.md](demo_xss_api.md) |

## Arquitectura de la Aplicacion

```
Proyecto-Ciberseguridad-2026/
├── api/
│   └── api_mock.py          # API de terceros simulada (puerto 5000)
├── backend/
│   ├── crear_perfil.php     # SSRF: file_get_contents sin validacion
│   └── ver_portafolio.php   # Consumo inseguro: confia en API sin sanitizar
├── frontend/
│   ├── index.html           # Formulario de perfil + vista de portafolio
│   ├── script.js            # XSS: innerHTML sin escape
│   └── styles.css
├── data/
│   └── usuarios.txt         # Almacenamiento de perfiles
├── router.php               # Router PHP built-in server
└── docs/
    ├── plan_app_vulnerable.md
    ├── payloads.txt         # <-- Fase 6.1
    ├── despliegue_vms.md    # <-- Fase 6.2
    ├── demo_ssrf.md         # <-- Fase 6.3
    ├── demo_xss_api.md      # <-- Fase 6.4
    └── README_demo.md       # <-- Este archivo
```

## Puertos y Servicios

| Servicio | Puerto | Comando de arranque |
|---|---|---|
| API Mock (Python) | 5000 | `python3 api/api_mock.py 5000` |
| App PHP | 8080 | `php -S 0.0.0.0:8080 router.php` |

## Rama Segura

El repositorio incluye la rama `versión-asegurada` con las mitigaciones
implementadas para cada vulnerabilidad. Usar como referencia en las secciones
de mitigacion de cada demo.

## Plan de Desarrollo Completo

Ver [plan_app_vulnerable.md](plan_app_vulnerable.md) para el plan completo
de las fases 1 a 6.
