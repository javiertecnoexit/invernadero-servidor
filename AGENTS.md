# AGENTS.md — Contexto del proyecto Invernadero IoT

Documento de contexto para retomar el trabajo. Leer siempre antes de modificar código.
Idioma de comunicación con el usuario: **español**.

## Qué es este proyecto
Servidor web PHP que recibe datos de un ESP32 (sensores de un invernadero de café
arábica) y muestra un **dashboard con gráficos Chart.js** para público no técnico.
Desplegado en la nube con **EasyPanel** (Docker), construido desde este repo GitHub.

## Repo y despliegue
- Repo local: `C:\Users\javier\Desktop\invernadero-servidor` (cambiar a la carpeta real si se mueve).
- Remoto: `https://github.com/javiertecnoexit/invernadero-servidor.git` — rama `main`.
- EasyPanel: proyecto **fitba** → servicio **cafetos** (contenedor PHP 8.2 + Apache).
- Deploy: en EasyPanel, botón verde **"Implementar"** sobre el servicio cafetos. El
  build tarda 1-3 min; el log debe terminar en `### Success`. Después recargar con **Ctrl+F5**.
- Dashboard: `https://fitba-cafetos.hltk7s.easypanel.host/`
- Importante: EasyPanel descarga el código como archive de GitHub → **el repo debe ser PÚBLICO**.

## Arquitectura (archivos)
| Archivo | Rol |
|---|---|
| `config.php` | Config: BD, API_KEY, DEVICE_ID_VALIDO, zona horaria. Lee variables de entorno (EasyPanel) con fallback a defaults. |
| `lecturas.php` | API de ingreso: `POST /lecturas.php` (ESP32). Auth por header `X-API-Key`. Inserta 1 fila por sensor, idempotente (ON DUPLICATE KEY). |
| `datos.php` | API de consulta: `GET /datos.php?rango=12h\|24h\|7d\|30d`. Devuelve series, stats, derivadas, comparación, zonas de riesgo, agregación diaria, correlaciones. |
| `umbrales.php` | Gestión de umbrales configurables. `GET` lista (siembra defaults), `POST` actualiza (requiere `X-API-Key`). Crea la tabla `umbrales` automáticamente. |
| `index.php` | Dashboard completo (HTML + CSS + JS Chart.js). Un solo archivo. |
| `Dockerfile` | Imagen `php:8.2-apache` + `pdo pdo_mysql` + `rewrite`. |

## Credenciales / constantes
- `API_KEY` (ESP32 + umbrales): `1c0ed2c4-4479-4952-ae74-336e586d759d` — debe coincidir con
  `API_KEY` del firmware (`invernadero/src/config.h`).
- `DEVICE_ID_VALIDO`: `invernadero_01`
- BD: host `fitba_invernadero_cafe`, user `invernadero_cafe`, pass `cafe2026`, db `invernadero_cafe`
  (todo sobreescribible con variables de entorno en EasyPanel).
- Zona horaria dashboard: `America/Argentina/Buenos_Aires` (BD guarda UTC).

## Esquema BD
- `lecturas(device_id, timestamp, sensor, valor, unidad, es_error)` — timestamp UTC,
  unicidad por `(device_id, timestamp, sensor)`, `valor` NULL si sensor en error.
- `umbrales(id, nombre UNIQUE, valor, unidad, activo, descripcion, actualizado_en)`
  — creada automáticamente por `umbrales.php`.

## Contrato API de entrada (lecturas.php)
`POST /lecturas.php` con header `X-API-Key` y body JSON:
```json
{
  "device_id": "invernadero_01",
  "timestamp": "2026-08-13T15:00:00Z",
  "lecturas": [ { "nombre": "Temp alto", "valor": 22.4, "unidad": "°C", "error": false } ]
}
```
Respuesta éxito: HTTP 200 `{"status":"ok"}`. El ESP32 solo marca "enviado" con ese HTTP 200.

## Sensores y derivadas
Sensores crudos: `Temp Ext`, `Temp alto`, `Temp bajo`, `Temp suelo`, `Hum alto`,
`Hum bajo`, `Hum suelo`, `Presion Ext`.

Derivadas (calculadas en `datos.php` por timestamp, tiempo local):
- `VPD` (kPa) = es·(1−H/100), `es=0.6108·exp(17.27·T/(T+237.3))`, con Temp alto + Hum alto.
- `Punto rocio` = T − (100−H)/5, con Temp bajo + Hum bajo.
- `Margen cond.` = Temp bajo − Punto rocio (margen de condensación).
- `Amortig. termica` = Temp bajo − Temp Ext (protección del invernadero).
- `Gradiente vert.` = Temp alto − Temp bajo (suavizado con media móvil ventana 6).
- `T interior` = (Temp alto + Temp bajo)/2.
- `H interior` = (Hum alto + Hum bajo)/2 (solo para correlaciones).
- `tasa_series`: tasa de cambio (°C/h) suavizada con media móvil de ventana ADAPTATIVA
  (`ventanaSuavizado()`: apunta a ~1 hora según frecuencia real de muestreo, clamp 4–24).
- `zonas_riesgo`: horas nocturnas (18:00–08:00) por zona térmica de `Temp bajo`
  (normal ≥15, atencion <15, estres <10, riesgo_severo <5, critico ≤0).
- `diario`: agregación min/max/avg por día para vistas 7d/30d.
- `comparacion`: 24h actuales vs 24h previas (solo si rango ≥ 24h).
- `correlaciones`: Pearson para `T ext vs T int`, `H suelo vs H int`, `T bajo vs T suelo`.

## Dashboard (index.php)
- Vistas: `12h`, `24h`, `7d`, `30d` (las dos últimas usan agregación diaria).
- 12 gráficos: Temperaturas (con líneas de umbral estrés/riesgo/crítico), Humedades,
  Amortiguación térmica, Horas por zona de riesgo, VPD (banda 0.4–0.8 kPa), Punto de
  rocío y margen (doble eje y2), Gradiente vertical, Tasa de cambio suavizada,
  Presión, Comparación 24h, 2 scatters por hora (T ext vs T int, H suelo vs H int).
- **Icono "i"** junto a cada título: tooltip explicativo en lenguaje simple
  (`EXPLICACIONES` + `inicializarInfo()`). Mantener al agregar/editar gráficos.
- **Aviso "Vista de días"** (`#aviso-diario`): explica promedio/banda mín-máx en 7d/30d.
- Leyenda oculta los datasets `(min)`/`(máx)` (filtro en `lineChart`); el rango se ve como banda.
- **Modal ⚙️ de umbrales**: lee/escribe `umbrales.php`; requiere API_KEY para guardar.
  Al guardar actualiza `UMBRALES` y refresca los gráficos.
- Umbrales por defecto en `UMBRALES_DEF` (índex) y `$DEFAULTS` (umbrales.php):
  buffer_termico_min 3°C, temp_bajo_estres 10°C, temp_bajo_riesgo 5°C,
  temp_bajo_critico 0°C, vpd_min 0.4 kPa, vpd_max 0.8 kPa, margen_rocio_min 2°C.

## Librerías CDN (jsDelivr)
- `chart.js@4.4.4` (UMD)
- `date-fns@3.6.0` + `chartjs-adapter-date-fns@3.0.0` (eje temporal)
- `chartjs-plugin-annotation@3.0.1` (líneas/bandas de umbral)

## Lecciones aprendidas / gotchas
- **chartjs-plugin-annotation se auto-registra** al cargar. NO llamar
  `Chart.register(Chart.Annotation)` a secas: `Chart.Annotation` es `undefined`
  (el global UMD es `chartjs-plugin-annotation`) y mata todo el script (botones + datos).
  Guardar con: `if (window.Chart && Chart.Annotation) { Chart.register(Chart.Annotation); }`.
- El error de PowerShell al pushear (`NativeCommandError`) es ruido de stderr de git;
  el push sí funciona (revisar la línea `old..new main -> main`).
- En EasyPanel el botón "Implementar" puede quedarse girando sin popup; refrescar la
  página (F5) y revisar la pestaña de logs del servicio. Causa típica: repo privado.
- Los archivos se versionan con `LF` (git normaliza a CRLF en Windows; avisar no es error).

## Flujo de trabajo típico
1. Editar archivos del repo local.
2. Verificar sintaxis PHP con el binario portable:
   `& "C:\Users\javier\AppData\Local\Temp\opencode\php83\php.exe" -l archivo.php`
   (también hay PHP en `C:\xampp\php\php.exe`).
3. `git add` + `git commit` con mensaje en español conciso.
4. `git push origin main`.
5. En EasyPanel: Implementar en servicio **cafetos** → esperar `### Success`.
6. Validar en el dashboard con **Ctrl+F5**. Si algo falla, pedir el error de la consola
   del navegador (F12 → Console) y los logs del servicio.

## Estado actual (fecha de este documento)
- Dashboard v3 desplegado y funcionando (12 gráficos + umbrales + tooltips + suavizado).
- Último commit local: `24eccf0` (push a origin/main OK).
- Pendientes posibles: nada urgente. (Actualizar esta sección tras cada cambio importante.)
