# FRN Stock & Prices

## 1.1.0 — Maestro semanal unificado, histórico y permisos

- Sustituye el flujo operativo de stock + tarifa importada por un único Excel semanal.
- Adapta el parser al formato real de Odoo con Nombre del producto, Referencia Interna, Precio de venta, Costo/Coste promedio, Categoria del producto, Stock y Unidad.
- Importa solo referencias con stock positivo; las referencias históricas ausentes permanecen en el maestro con stock 0.
- Conserva un histórico semanal de stock, precio de origen, precio comercial y coste promedio.
- Mantiene el precio comercial editable sin sobrescribirlo en cada importación semanal.
- Separa Precio origen, Precio comercial y Coste promedio.
- Protege el coste promedio mediante capability: quien no tiene permiso no lo ve ni puede exportarlo.
- Genera Carne y Pescado / Marisco por separado desde el mismo maestro.
- Añade roles FRN Administrador, FRN Stock, FRN Director Comercial, FRN Comercial y FRN Consulta.
- Añade administración de usuarios desde el frontend de /stock/.
- Mantiene bloqueado wp-admin para perfiles FRN no administradores de WordPress.
- Muestra la versión instalada en la cabecera, hero y pie de la aplicación.
- Mantiene compatibilidad de lectura con listas de precios antiguas, pero ya no forman parte del flujo normal.
- El PDF/CSV sigue excluyendo estrictamente líneas destildadas y nunca imprime precio 0.

## 1.0.0 — Stock y tarifas comerciales separados

- Separa importación de STOCKS e importación de TARIFAS DE PRECIOS.
- Mantiene el stock vigente independientemente de las listas comerciales.
- Permite guardar varias tarifas de precios por cliente, zona o campaña.
- Crea PDFs combinando stock vigente + tarifa elegida.
- Los productos ausentes del stock semanal o con stock 0 quedan destildados.
- Los códigos XXX / XXXX / XXXXX... se mantienen como Próximos ingresos.
- El PDF filtra estrictamente líneas destildadas.
- Descargar PDF o CSV guarda primero las modificaciones actuales.
- Los precios 0 se muestran vacíos, nunca como 0,00 €.
- Añade bandas alternas claro/oscuro en la tabla PDF.
- Próximos ingresos aparece siempre, incluso sin referencias.
- Mantiene PDFs separados para Carne y Pescado / Marisco.
- La operación sigue 100% en frontend para usuarios FRN Comercial.

## 0.9.0 — Tarifa semanal operativa

- Stock 0 queda destildado por defecto para productos normales.
- Próximos ingresos XXX permanecen disponibles para selección sin stock.
- Tarifa Carne y Tarifa Pescado / Marisco se generan por separado.
- Añade presets General, Distribuidor, Disponibilidad y Personalizado.
- Añade controles masivos de selección y oferta.
- Sincroniza Mostrar stock / Mostrar precio con los checks de cada línea.
- El PDF oculta columnas completas cuando stock o precio están desactivados.
- Simplifica la edición quitando Estado/Borrador/Final de la interfaz.
- Mantiene una única fila de acciones al final de la tarifa.

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
