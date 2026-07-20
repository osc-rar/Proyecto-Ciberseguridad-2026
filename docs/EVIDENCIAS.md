# Evidencias del Ataque — Plantilla y Checklist

Proyecto de Ciberseguridad UCAB 2026 — rama `versión-vulnerable`.

Guia para que el **Red Team** organice las evidencias reales de la demo (logs,
capturas de pantalla, salidas de `curl`) generadas al ejecutar los ataques en las
VMs. Indica **que capturar en cada paso** de [demo_ssrf.md](demo_ssrf.md) y
[demo_xss_api.md](demo_xss_api.md).

> Las evidencias se generan en las VMs reales. Este documento no las contiene: solo
> define que recolectar y donde guardarlo.

## Estructura de carpetas sugerida

```
evidencias/
├── conectividad/     # ping/nmap previos — ver PRUEBA_CONECTIVIDAD.md
├── ssrf/             # evidencias del ataque SSRF (demo_ssrf.md)
└── xss/              # evidencias del ataque XSS via API (demo_xss_api.md)
```

Sugerencia de nombres: `evidencias/ssrf/paso2_localhost_5000.png`,
`evidencias/xss/paso4_alert_domain.png`, `evidencias/ssrf/paso4_etc_passwd.txt`.

---

## A. Evidencias SSRF — `evidencias/ssrf/` (ver [demo_ssrf.md](demo_ssrf.md))

| # | Referencia | Que capturar | Archivo sugerido |
|---|---|---|---|
| A1 | Paso 1 de demo_ssrf.md | Salida del baseline: JSON con `estado_conexion: ok` y `preview` de una URL legitima | `ssrf/paso1_baseline.txt` |
| A2 | Paso 2 de demo_ssrf.md | Captura de pantalla mostrando el JSON con `preview_repositorio` conteniendo el JSON interno de la API mock (`127.0.0.1:5000/repos`) | `ssrf/paso2_localhost_5000.png` |
| A3 | Paso 3 de demo_ssrf.md | Tabla/capturas comparando `estado_conexion` para puertos 5000, 22 y 9999 (oraculo de escaneo) | `ssrf/paso3_escaneo_puertos.png` |
| A4 | Paso 4 de demo_ssrf.md | Salida mostrando `preview_repositorio` con contenido de `file:///etc/passwd` | `ssrf/paso4_etc_passwd.txt` |
| A5 | Paso 4 (variante) | Salida leyendo `data/usuarios.txt` via `file://` | `ssrf/paso4_usuarios_txt.txt` |
| A6 | Servidor victima | Log/terminal del servidor PHP mostrando las peticiones recibidas durante el ataque (si esta disponible) | `ssrf/servidor_php_log.txt` |

---

## B. Evidencias XSS via API — `evidencias/xss/` (ver [demo_xss_api.md](demo_xss_api.md))

| # | Referencia | Que capturar | Archivo sugerido |
|---|---|---|---|
| B1 | Paso 1 de demo_xss_api.md | Estado limpio: navegador mostrando los 2 repos legitimos + salida `curl` del baseline | `xss/paso1_baseline.png` |
| B2 | Paso 2 de demo_xss_api.md | Salida de `POST /update_repos` confirmando `status: repositorios actualizados` con la `descripcion` maliciosa | `xss/paso2_update_repos.txt` |
| B3 | Paso 3 de demo_xss_api.md | Salida de `ver_portafolio.php` mostrando que el backend **propaga** el payload sin sanitizar | `xss/paso3_backend_propaga.txt` |
| B4 | Paso 4 de demo_xss_api.md | Captura de pantalla del `alert(document.domain)` ejecutandose en el navegador de la victima (el dominio visible prueba el contexto) | `xss/paso4_alert_domain.png` |
| B5 | Paso 5b de demo_xss_api.md (opcional) | Terminal del listener del atacante (`python3 -m http.server 8000`) recibiendo la peticion con `document.cookie` | `xss/paso5_robo_cookie.png` |
| B6 | Post-demo | Salida de reset confirmando que `/repos` volvio a los 2 repos originales | `xss/reset_estado_limpio.txt` |

---

## C. Evidencias de conectividad — `evidencias/conectividad/`

Ver [PRUEBA_CONECTIVIDAD.md](PRUEBA_CONECTIVIDAD.md): capturas/outputs de `ping` y
`nmap -p 8080,5000` demostrando visibilidad de la victima antes del ataque.

---

## Checklist final de evidencias

- [ ] Conectividad: `ping` + `nmap` capturados (`evidencias/conectividad/`)
- [ ] SSRF: A1–A5 capturados (A6 si el log esta disponible)
- [ ] XSS: B1–B4 capturados (B5 opcional; B6 recomendado)
- [ ] Cada archivo nombrado de forma consistente y guardado en su subcarpeta
- [ ] Todas las capturas incluyen contexto visible (URL/IP de la victima, terminal)

> **Recomendacion:** registrar la fecha/hora de la demo y la IP real de la victima
> en un `evidencias/README.txt` para trazabilidad.
