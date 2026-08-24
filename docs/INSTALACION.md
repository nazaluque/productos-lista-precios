# Instalación en WordPress

1. Copiar `frn-stock-prices/` a `wp-content/plugins/`.
2. Ejecutar `composer install --no-dev --optimize-autoloader` dentro del plugin.
3. Activar **FRN Stock & Prices** en WordPress.
4. Guardar una vez los enlaces permanentes para confirmar las rutas.
5. En la portada actual, insertar únicamente el shortcode `[frn_home_buttons]` donde deban aparecer los dos botones.

Rutas creadas:

- `/stock/pescado-marisco/`
- `/stock/carne/`

El plugin no reemplaza el Home ni modifica el tema activo.

## Protección futura

La opción `frn_sp_catalog_protection_enabled` queda creada con valor `false`. Cuando se active, las landings exigirán un usuario WordPress autenticado. En esta primera versión permanece desactivada.
