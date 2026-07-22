# Proyecto Ciberseguridad 2026

Esta es una aplicacion web hecha para aprender sobre dos vulnerabilidades de seguridad en APIs y como corregirlas, usando como sistema para evidenciarlo una aplicación web básica donde los usuarios pueden crear un perfil público para mostrar su experiencia en el campo de la informática. La aplicación tendrá dos funcionalidades principales que albergarán las vulnerabilidades. La publicación de los repositorios del usuario mediante el consumo de una API que contendrá la información y la capacidad de colocar su foto de perfil de forma automática agregando cualquier link que contenga un perfil del cual poder sacarla

El codigo vive en las ramas: en `versión-vulnerable` esta la aplicacion tal como es insegura, y en `versión-asegurada` esta la misma aplicacion con las correcciones aplicadas

## 1. Stack tecnologico

Para poder desarrollar la idea anterior, estas son las tecnologias que usadas:

- **PHP**: corre el backend y sirve el frontend usando el servidor embebido de PHP junto con el archivo `router.php`. Su elección viene debido a que permite concatenar variables directamente en las consultas o en el HTML de forma nativa e intuitiva, cumpliendo perfectamente con los requisitos de la rúbrica
- **Python 3**: levanta una API simulada (`api/api_mock.py`) que devuelve la lista de repositorios que consume la aplicacion
- **HTML, CSS y JavaScript**: forman el frontend (`frontend/index.html`, `frontend/styles.css`, `frontend/script.js`)
- **Archivo de texto plano**: los perfiles de usuario se guardan en `data/usuarios.txt`, no se usa base de datos
- **Infraestructura:** VirtualBox, con una máquina virtual atacante con Kali Linux y una máquina virtual víctima (Ubuntu Server) alojando los servicios

Los puertos que ocupa cada servicio son:

| Servicio | Tecnologia | Puerto |
|---|---|---|
| Aplicacion PHP (frontend y backend) | PHP | 8080 |
| API simulada | Python 3 | 5000 |

## 2. Manual de despliegue

### Requisitos previos

Antes de empezar hay que tener instalado:

- Python 3.8 o superior
- PHP 8.0 o superior
- Git

Se pueden revisar las versiones instaladas con estos comandos:

```bash
python3 --version
php --version
```

### Paso 1: Clonar la rama

Se clona la rama que se quiere probar, por ejemplo la version vulnerable:

```bash
git clone -b versión-vulnerable https://github.com/osc-rar/Proyecto-Ciberseguridad-2026.git
cd Proyecto-Ciberseguridad-2026
```

Para probar la version asegurada se usa el mismo comando pero cambiando el nombre de la rama por `versión-asegurada`

### Paso 2: Preparar el archivo de datos

Se crea la carpeta y el archivo donde se guardan los perfiles, por si todavia no existen:

```bash
mkdir -p data
touch data/usuarios.txt
```

### Paso 3: Levantar la API en Python (puerto 5000)

En una primera terminal se arranca la API simulada:

```bash
python3 api/api_mock.py 5000
```

Si todo sale bien aparece un mensaje avisando que la API esta corriendo en el puerto 5000

### Paso 4: Levantar el servidor PHP (puerto 8080)

En una segunda terminal, sin cerrar la primera, se arranca la aplicacion:

```bash
php -S 0.0.0.0:8080 router.php
```

### Paso 5: Abrir la aplicacion

Se abre el navegador en esta direccion:

```
http://localhost:8080/
```

Los dos servicios tienen que estar corriendo al mismo tiempo, porque el backend le pide la lista de repositorios a la API que esta en el puerto 5000

> **Aviso**: esta aplicacion es vulnerable a proposito, solo se debe usar en un entorno aislado de laboratorio y nunca hay que exponer los puertos 8080 o 5000 a internet
