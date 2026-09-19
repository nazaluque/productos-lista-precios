# FRN Stock & Prices

Herramienta privada de **frnatlantico.es** para preparar stock, precios y tarifas comerciales de FRN Atlántico.

La web corporativa **frnatlantico.com no forma parte de este proyecto**.

## Arquitectura 1.0

La aplicación funciona 100% en frontend:

- `/stock/acceso/` — login privado.
- `/stock/` — herramienta interna.
- **Datos semanales** — Stocks + Tarifas de precios.
- **Crear PDF** — combina stock vigente y lista comercial.

## Dos fuentes independientes

### STOCKS

Se importa el archivo semanal de Odoo.

- Actualiza cantidades.
- Un producto normal con stock 0 queda destildado.
- Un producto que no aparece en el stock de esa semana queda con stock 0 y destildado.
- No modifica las tarifas comerciales.

### TARIFAS DE PRECIOS

Se importa otro Excel independiente.

Cada importación se guarda con un nombre, por ejemplo:

- General 21/09/2026
- Valdepeice
- Madrid
- HORECA Norte

Una tarifa de precios no modifica el stock.

## Generación de PDF

Al crear una tarifa se selecciona:

1. Carne o Pescado / Marisco.
2. Tarifa de precios.
3. Formato General, Distribuidor, Disponibilidad o Personalizado.

La aplicación crea un snapshot editable.

Reglas:

- línea destildada = no sale;
- precio 0 = celda vacía;
- stock oculto = columna eliminada;
- precio oculto = columna eliminada;
- PDF/CSV guardan los cambios antes de exportar;
- filas con bandas alternas para lectura;
- Próximos ingresos aparece siempre.

## Próximos ingresos

Código formado solo por tres o más X:

- XXX
- XXXX
- XXXXX

Se clasifica como **Próximo ingreso** aunque todavía no tenga stock.

## Seguridad

- Rol `FRN Comercial`.
- Sin acceso al wp-admin para ese rol.
- Aplicación `noindex, nofollow, noarchive`.
- Administradores mantienen acceso normal a WordPress.

## Excel

El parser acepta XLSX/XLS y detecta columnas habituales de:

- código / referencia / SKU;
- producto / descripción;
- stock / cantidad / existencia;
- precio / tarifa / €/kg;
- marca;
- oferta;
- visible/publicar.

Cuando se disponga del export definitivo de Odoo se pueden afinar aliases sin cambiar la arquitectura.
