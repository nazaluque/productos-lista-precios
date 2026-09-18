# FRN Stock & Prices

## 0.6.0 — Tarifas semanales y PDF

- Añade un módulo independiente **Tarifas semanales** dentro de FRN Stock.
- Crea snapshots del catálogo actual para conservar el histórico de cada semana.
- Mantiene por separado el stock/precio de origen y el stock/precio mostrado en la tarifa.
- Permite incluir u ocultar cada referencia.
- Permite mostrar u ocultar stock y precio globalmente y por producto.
- Permite stock exacto, redondeado, texto “Disponible” u oculto.
- Permite marcar productos como oferta y ordenar manualmente las líneas.
- Añade datos comerciales editables para los documentos.
- Añade descarga de PDF real con Dompdf.
- Añade descarga CSV.
- El PDF intenta usar el logo configurado en WordPress y mantiene la identidad FRN negro/dorado.
- No modifica la web corporativa .com.

## 0.5.0 — Importación única y reparación de enlaces

- Elimina el selector de categoría del importador.
- Una sola carga del Excel actualiza Carne y Pescado / Marisco.
- Previsualiza y publica las dos categorías conjuntamente.
- Reconoce las rutas `/stock/` aunque WordPress no haya regenerado los enlaces permanentes.
- Añade un botón manual para reparar enlaces y accesos de comprobación.
- Identifica claramente la versión del plugin frente a la versión de WordPress.

## 0.4.0 — Importación completa y frontend separado

- Corrige definitivamente la lectura de miles y decimales del stock desde Excel.
- Importa todas las referencias, tengan o no precio o stock positivo.
- Añade selector Visible Sí/No en el editor de WordPress.
- Muestra «Consultar precio» y «Consultar stock» sin ocultar el producto.
- Separa la portada en el plugin FRN Home 1.0.0.
- Añade portada fotográfica, menú completo, teléfono y WhatsApp.

## 0.3.0 — Normalización, edición y navegación

- Corrige la lectura errónea del año 2026 como precio.
- Los productos sin coincidencia exacta quedan con precio 0 y se muestran como «Consultar precio».
- Permite editar y guardar manualmente código, marca, producto, stock y precio desde FRN Stock.
- Añade el centro «Productos y stock» en `/stock/`.
- Añade navegación visible desde el Home y las landings.
- Añade teléfono y WhatsApp en navegación, catálogo y pie.
