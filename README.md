# FRN Stock & Prices

Herramienta privada de **frnatlantico.es** para preparar stock, precios y tarifas comerciales de FRN Atlántico.

La web corporativa **frnatlantico.com no forma parte de este proyecto y no debe modificarse**.

## Arquitectura 1.1.0

La aplicación funciona 100% en frontend:

- `/stock/acceso/` — login privado.
- `/stock/` — herramienta interna.
- **Datos semanales** — un único Excel semanal.
- **Crear PDF** — Carne y Pescado / Marisco por separado.
- **Usuarios** — administración frontend para perfiles autorizados.

La versión instalada se muestra dentro de la aplicación.

## Excel semanal

El flujo normal utiliza un único archivo Odoo. El parser admite XLSX/XLS, varias hojas y varios archivos.

Cabeceras soportadas, entre otras:

- Nombre del producto / Producto / Descripción
- Referencia Interna / Código / Referencia / SKU
- Marca
- Modelo
- Categoria del producto / Categoría / Familia
- Precio de venta / Precio
- Costo / Coste / Costo promedio / Coste promedio
- Stock / Cantidad a la mano / Existencia
- Unidad / Unidad de medida

Reglas:

- producto normal con stock > 0: se importa;
- stock 0 o vacío: no entra en la disponibilidad semanal;
- producto ya conocido que desaparece del archivo: permanece en el maestro e histórico, pero queda stock 0 y oculto;
- el código es la identidad principal del producto;
- códigos XXX / XXXX / XXXXX... son Próximos ingresos;
- hojas mixtas Carne + Pescado se clasifican por Categoria del producto y, como respaldo, por prefijo C/P.

## Maestro e histórico

Cada producto conserva:

- código;
- marca;
- nombre;
- categoría;
- unidad;
- stock vigente;
- precio origen;
- precio comercial editable;
- coste promedio;
- estado visible/oferta;
- histórico de importaciones.

El histórico guarda stock, precio origen, precio comercial y coste promedio de cada importación.

## Precios

El precio comercial vive en el maestro y puede editarse por perfiles autorizados.

La importación semanal actualiza **precio origen** y **coste promedio**, pero no pisa un precio comercial ya editado. Si un producto nuevo no tiene precio comercial todavía, se inicializa con el precio de origen cuando existe.

Precio 0 o vacío nunca se imprime como 0,00 €.

## Coste promedio

El coste solo se muestra y exporta si el usuario tiene la capability `frn_view_cost`.

Quien no tiene ese permiso:

- no ve coste en la tabla;
- no ve coste al preparar la tarifa;
- no puede activarlo;
- no lo recibe en PDF ni CSV.

## Roles

- **FRN Administrador** — stock, precios, coste, exportación y usuarios.
- **FRN Stock** — importa/edita stock y exporta; sin coste.
- **FRN Director Comercial** — edita precios, ve/exporta coste y exporta.
- **FRN Comercial** — consulta y exporta; sin coste.
- **FRN Consulta** — consulta y exporta; sin edición ni coste.

Los perfiles FRN trabajan en frontend y no necesitan acceso operativo a wp-admin.

## PDF / CSV

Al crear una tarifa se selecciona:

1. fecha;
2. Carne o Pescado / Marisco;
3. preset General, Distribuidor, Disponibilidad o Personalizado.

Después puede decidirse:

- mostrar/ocultar stock;
- stock exacto, redondeado o “Disponible”;
- mostrar/ocultar precio;
- mostrar/ocultar coste promedio, solo con permiso;
- incluir/excluir líneas;
- marcar ofertas;
- ordenar.

Reglas críticas:

- línea destildada = no sale;
- PDF/CSV guardan primero el estado actual;
- precio 0 = vacío;
- coste 0 = vacío;
- Próximos ingresos aparece siempre;
- Carne y Pescado / Marisco nunca se mezclan en el mismo PDF.

## Compatibilidad

Las tablas y listas de precios anteriores se conservan para no romper instalaciones existentes, pero desde 1.1.0 ya no son necesarias para el flujo operativo normal.
