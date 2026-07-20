# Organizacion del Equipo

Proyecto de Ciberseguridad UCAB 2026 — aplicacion "Portafolio Dev".

El equipo se organiza en dos roles complementarios: **Red Team** (ataque) y
**Blue Team** (desarrollo y defensa).

## Red Team — Ataque y evidencias

| Integrante | Responsabilidad |
|---|---|
| Levin Jimenez | Ejecucion de los ataques (SSRF y XSS via API) y recoleccion de evidencias |
| Jesus Sayago | Ejecucion de los ataques (SSRF y XSS via API) y recoleccion de evidencias |

**Responsabilidades del rol:**
- Preparar y verificar la conectividad hacia la victima
  ([PRUEBA_CONECTIVIDAD.md](PRUEBA_CONECTIVIDAD.md)).
- Ejecutar los guiones de ataque ([demo_ssrf.md](demo_ssrf.md),
  [demo_xss_api.md](demo_xss_api.md)) usando el catalogo de
  [payloads.txt](payloads.txt) y el script [payloads.sh](payloads.sh).
- Generar y organizar las evidencias segun [EVIDENCIAS.md](EVIDENCIAS.md).

## Blue Team — Desarrollo y defensa

| Integrante | Responsabilidad |
|---|---|
| Claudia Lopez | Desarrollo de ambas ramas (vulnerable y asegurada) y documentacion de contramedidas |
| Oscar Manrique | Desarrollo de ambas ramas (vulnerable y asegurada) y documentacion de contramedidas |
| Juan Zamora | Desarrollo de ambas ramas (vulnerable y asegurada) y documentacion de contramedidas |

**Responsabilidades del rol:**
- Desarrollar la rama `versión-vulnerable` (app con las dos vulnerabilidades
  intencionales) y la rama `versión-asegurada` (mitigaciones).
- Documentar la arquitectura y el analisis
  ([ARQUITECTURA_COMPONENTES.md](ARQUITECTURA_COMPONENTES.md),
  [INVENTARIO_ENDPOINTS.md](INVENTARIO_ENDPOINTS.md),
  [FLUJO_DATOS.md](FLUJO_DATOS.md), [ANALISIS_IMPACTO.md](ANALISIS_IMPACTO.md)).
- Definir e implementar las contramedidas (tablas de "Mitigacion" en las demos y
  codigo de la rama `versión-asegurada`).

## Colaboracion entre roles

El Red Team valida en la practica que las vulnerabilidades introducidas por el
Blue Team son explotables, y el Blue Team usa esas evidencias para justificar y
verificar las mitigaciones. El mapeo formal de la cadena de ataque esta en
[METODOLOGIA_MITRE_ATTACK.md](METODOLOGIA_MITRE_ATTACK.md).
