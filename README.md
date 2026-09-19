# FRN Stock & Prices

Herramienta privada de **frnatlantico.es** para preparar stock, precios y tarifas comerciales semanales de FRN Atlántico.

La web corporativa **frnatlantico.com no forma parte de este proyecto**.

## Arquitectura 0.8

La operación diaria ocurre 100% en el frontend:

- `/stock/acceso/` — login privado FRN.
- `/stock/` — aplicación interna.
- Tab **Importar Excel semanal**.
- Tab **Crear PDF**.

Los usuarios con rol **FRN Comercial** no acceden al panel general de WordPress.

## Flujo semanal

1. Subir uno o varios Excel de Odoo.
2. Previsualizar.
3. Publicar datos.
4. Editar en una tabla:
   - código;
   - marca;
   - producto;
   - stock;
   - precio;
   - oferta;
   - incluir/excluir.
5. Crear una tarifa semanal.
6. Ajustar qué se imprime.
7. Descargar PDF o CSV.

## Próximos ingresos

Una referencia cuyo código esté formado exclusivamente por **3 o más X** se considera un producto todavía sin código definitivo:

- `XXX`
- `XXXX`
- `XXXXX`
- etc.

Se guarda como **Próximo ingreso**, aparece debajo de los productos actuales y el PDF crea una sección **PRÓXIMOS INGRESOS**.

Se permiten varias referencias distintas con el mismo código provisional `XXX`.

## Seguridad

- Login WordPress.
- Rol específico `FRN Comercial`.
- Sin acceso al wp-admin para ese rol.
- Aplicación privada con `noindex, nofollow, noarchive`.
- Páginas marcadas para no ser cacheadas.
- Administradores conservan su acceso normal a WordPress.

## Excel

Mientras se termina de adaptar al export exacto de Odoo, el parser acepta:

- `CARNE_IMPORT` / `PESCADO_IMPORT`;
- uno o varios Excel;
- detección por nombre de archivo o pestaña;
- detección auxiliar por códigos C... y P....

No guardar stocks ni precios confidenciales en GitHub.
