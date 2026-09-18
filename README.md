# FRN Stock & Prices

Aplicación WordPress para gestionar el stock semanal, precios B2B y tarifas comerciales de FRN Atlántico en **frnatlantico.es**.

> Este repositorio es independiente de la web corporativa `.com`. La implementación de tarifas se desarrolla aquí para no mezclarla con `nazaluque/frn-atlantico-web`.

## Flujo semanal

1. Importar el Excel semanal desde **FRN Stock**.
2. Previsualizar y publicar el catálogo base.
3. Abrir **FRN Stock → Tarifas semanales**.
4. Crear una tarifa a partir del catálogo actual.
5. Elegir qué productos aparecen y qué columnas se muestran.
6. Ajustar precio o stock solo para esa tarifa, sin modificar el dato original.
7. Marcar ofertas si corresponde.
8. Descargar el PDF comercial o el CSV.
9. La tarifa queda guardada en el histórico.

## Catálogos públicos

- `/stock/` — centro de productos y stock.
- `/stock/pescado-marisco/` — Pescado y Marisco.
- `/stock/carne/` — Carne.

## Tarifa semanal v0.6

La versión 0.6 añade:

- histórico de tarifas;
- snapshot semanal para no perder tarifas anteriores;
- precio origen vs. precio de tarifa;
- stock origen vs. stock de tarifa;
- mostrar/ocultar precio;
- mostrar/ocultar stock;
- stock exacto, redondeado, “Disponible” u oculto;
- incluir/excluir productos;
- marcar ofertas;
- orden manual;
- exportación PDF real mediante Dompdf;
- exportación CSV;
- datos de empresa editables desde WordPress;
- uso automático del logo configurado en WordPress cuando sea PNG/JPG.

## Importación de Odoo

El importador actual espera el Excel maestro con las pestañas `CARNE_IMPORT` y `PESCADO_IMPORT`.

La interfaz de tarifas ya no depende de ese formato. Cuando dispongamos del **Excel real exportado por Odoo**, se adapta únicamente el parser de importación; el editor, el histórico y los PDF no cambian.

## Plugins

- **FRN Stock & Prices**: importación, catálogo, editor de tarifas y exportación.
- **FRN Home**: portada comercial del dominio .es.

No guardar en este repositorio listas de precios o stocks confidenciales de producción. Los Excel reales se cargan desde WordPress y viven en la base de datos/hosting, no en GitHub.
