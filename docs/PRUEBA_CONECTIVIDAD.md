# Prueba de Conectividad (Pre-Ataque)

Proyecto de Ciberseguridad UCAB 2026 — rama `versión-vulnerable`.

Comandos para **demostrar que la VM Kali (atacante) tiene visibilidad de la VM
victima** antes de iniciar los ataques. Esta evidencia debe generarse en las VMs
reales; los bloques `[PEGAR AQUI ...]` son placeholders para que el equipo pegue el
output o las capturas.

- Requiere las VMs desplegadas segun [despliegue_vms.md](despliegue_vms.md)
  (red host-only, IPs asignadas, servicios arrancados).
- IPs de ejemplo (ajustar a las reales): **Kali (atacante)** `192.168.56.101`,
  **Ubuntu Server (victima)** `192.168.56.100`.

```bash
# Variable usada en todos los comandos (ejecutar en la terminal de Kali)
VICTIMA=192.168.56.100
```

---

## 1. Ping — la victima responde en la red host-only

```bash
ping -c 4 $VICTIMA
```

**Que demuestra:** hay conectividad IP entre Kali y la victima en la red aislada.

```
[PEGAR AQUI EL OUTPUT DE ping]
```

**Captura de pantalla:**

```
[PEGAR AQUI LA CAPTURA DE PANTALLA DE ping — guardar en evidencias/conectividad/]
```

---

## 2. nmap — los puertos de la app (8080) y la API (5000) estan abiertos

```bash
nmap -p 8080,5000 $VICTIMA
```

**Que demuestra:** desde Kali son alcanzables el servidor PHP (8080) y la API mock
(5000), los dos servicios que se atacan en las demos.

**Salida esperada (orientativa):** ambos puertos en estado `open`.

```
[PEGAR AQUI EL OUTPUT DE nmap]
```

**Captura de pantalla:**

```
[PEGAR AQUI LA CAPTURA DE PANTALLA DE nmap — guardar en evidencias/conectividad/]
```

### Opcional — deteccion de servicios/versiones

```bash
nmap -sV -p 8080,5000 $VICTIMA
```

```
[PEGAR AQUI EL OUTPUT DE nmap -sV]
```

---

## 3. Verificacion HTTP de los servicios

Confirma que los servicios no solo tienen el puerto abierto, sino que responden a
nivel de aplicacion. (Estos comandos coinciden con el checklist de
[despliegue_vms.md](despliegue_vms.md) §5.)

```bash
# 3.1 API mock responde con los 2 repos de ejemplo
curl -s http://$VICTIMA:5000/repos | python3 -m json.tool

# 3.2 Frontend accesible (codigo HTTP esperado: 200)
curl -s -o /dev/null -w "%{http_code}\n" http://$VICTIMA:8080/

# 3.3 Backend de portafolio responde con JSON
curl -s http://$VICTIMA:8080/backend/ver_portafolio.php | python3 -m json.tool
```

```
[PEGAR AQUI EL OUTPUT DE LOS 3 COMANDOS curl]
```

---

## Checklist de conectividad

- [ ] `ping` a la victima responde (0% packet loss) — output y captura pegados
- [ ] `nmap` muestra `8080/open` y `5000/open` — output y captura pegados
- [ ] `curl` a `:5000/repos` devuelve los 2 repos de ejemplo
- [ ] `curl` a `:8080/` devuelve `200`
- [ ] `curl` a `:8080/backend/ver_portafolio.php` devuelve JSON

Una vez completado, continuar con [demo_ssrf.md](demo_ssrf.md) y
[demo_xss_api.md](demo_xss_api.md). Organizar las evidencias segun
[EVIDENCIAS.md](EVIDENCIAS.md).
