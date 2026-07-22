# Proyecto-Ciberseguridad-2026

Proyecto de Ciberseguridad UCAB 2026.

Aplicación web **intencionalmente vulnerable** que demuestra, de forma didáctica y
en un laboratorio aislado, dos riesgos del **OWASP API Security Top 10 (2023)** y
sus mitigaciones. El repositorio contiene la versión vulnerable, la versión
asegurada y la validación de cierre que contrasta ambas.

> **ADVERTENCIA:** El código es vulnerable por diseño. Usar exclusivamente en
> entornos controlados y aislados (VMs de laboratorio host-only) con el único propósito de probar y entender cómo funcionan estas dos vulnerabilidades dentro de una aplicación. Nunca exponer
> los puertos 8080 o 5000 a redes públicas o de producción. Está principalmente disenada para ser alojada en una VM con Ubuntu Server.

## ¿Qué es?

Un portafolio de repositorios donde un usuario crea su perfil y consulta una lista
de repositorios servida por una API externa. Ese flujo simple concentra dos
vulnerabilidades reales de APIs, que un equipo Red Team explota y un equipo Blue
Team corrige y verifica.

## Las dos vulnerabilidades

| # | Vulnerabilidad | OWASP API Top 10 (2023) | CWE | Archivo |
|---|---|---|---|---|
| 1 | Server Side Request Forgery (SSRF) | API7:2023 | CWE-918 | `backend/crear_perfil.php` |
| 2 | Consumo No Seguro de APIs → Cross-Site Scripting | API10:2023 | CWE-20 → CWE-79 | `backend/ver_portafolio.php`, `frontend/script.js` |

- **SSRF:** el campo `url_repositorio` llega sin validar a `file_get_contents()`,
  permitiendo forzar peticiones a servicios internos y leer archivos locales.
- **Consumo No Seguro → XSS:** el backend confía ciegamente en la respuesta de una
  API de terceros y el contenido se renderiza sin escapar, ejecutando JavaScript
  en el navegador de la víctima.

## Estructura del proyecto

```
├── api/api_mock.py              # API de terceros simulada (Python, puerto 5000)
├── backend/
│   ├── crear_perfil.php         # Endpoint del vector SSRF
│   └── ver_portafolio.php       # Consumo de la API externa / renderizado
├── frontend/
│   ├── index.html               # UI del portafolio
│   ├── script.js                # Renderizado del portafolio
│   └── styles.css
├── data/usuarios.txt            # Almacenamiento de perfiles
├── router.php                   # Router para el servidor embebido de PHP
└── docs/                        # Documentación técnica y guías de demo
```

## Inicio rápido

Se necesitan dos terminales en la máquina que aloja la app:

```bash
# Terminal 1: API Mock
python3 api/api_mock.py 5000

# Terminal 2: Aplicación PHP
php -S 0.0.0.0:8080 router.php
```

Abrir `http://localhost:8080/` en el navegador. Para el despliegue completo en
VMs Kali + Ubuntu, ver las guías de cada rama más abajo.

## Ramas del repositorio

| Rama | Qué contiene |
|---|---|
| `main` | Punto de entrada del proyecto: este README y la validación de cierre. |
| `versión-vulnerable` | Implementación con las vulnerabilidades intencionales y las guías de explotación (demos SSRF y XSS, payloads). |
| `versión-asegurada` | Misma aplicación con las mitigaciones aplicadas (allowlist SSRF, validación y escape del consumo de API). |
| `docs/documentacion-rama-vulnerable` | Documentación técnica de la versión vulnerable: arquitectura, endpoints, MITRE ATT&CK, flujo de datos, impacto y evidencias. |
| `docs/comentarios-y-documentacion` | Documentación técnica de la versión asegurada: justificaciones OWASP/CWE de cada corrección. |

## Documentación clave

Los enlaces usan URLs completas para que funcionen desde cualquier rama.

### Versión vulnerable

- [Flujo de datos de las vulnerabilidades](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/FLUJO_DATOS.md)
- [Arquitectura y componentes](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/ARQUITECTURA_COMPONENTES.md)
- [Inventario de endpoints](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/INVENTARIO_ENDPOINTS.md)
- [Metodología MITRE ATT&CK](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/METODOLOGIA_MITRE_ATTACK.md)
- [Análisis de impacto](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/ANALISIS_IMPACTO.md)
- [Evidencias de las pruebas](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/EVIDENCIAS.md)
- [Catálogo de payloads](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/payloads.txt)
- Guías de demo: [SSRF](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/demo_ssrf.md) · [XSS vía API](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/demo_xss_api.md)

### Versión asegurada

- [Documentación técnica de las mitigaciones (OWASP/CWE)](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/comentarios-y-documentacion/docs/DOCUMENTACION_TECNICA.md)

### Validación de cierre

- [VALIDACION_CIERRE.md](VALIDACION_CIERRE.md) — contraste payload a payload de cada
  vulnerabilidad entre la versión vulnerable (exploit funciona) y la asegurada
  (bloqueado/neutralizado), con la función real responsable de cada mitigación.

## Equipo

**Red Team (explotación)**

- Levin Jiménez
- Jesús Sayago

**Blue Team (mitigación y verificación)**

- Claudia López
- Óscar Manrique
- Juan Zamora
