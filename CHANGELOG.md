# FRN Stock & Prices

## 1.1.13 — Precio de venta semanal sincronizado

- Corrige la importación semanal unificada: la columna “Precio de venta” del Excel actualiza siempre el precio comercial del maestro.
- Los productos existentes ya no conservan un precio comercial antiguo cuando el Excel trae un precio nuevo.
- Si “Precio de venta” viene a 0, el precio comercial también se limpia a 0 y el PDF muestra “Consultar precio”.
- El Director Comercial puede modificar el precio comercial después de importar y antes de crear/exportar la tarifa.
- Stock, coste promedio, nombre, marca, código e histórico mantienen el comportamiento actual.

## 1.1.6 — Branding PDF robusto en Media Library

- Corrige la causa raíz de las cabeceras vacías: los JPG/PNG empaquetados en versiones anteriores estaban corruptos/truncados.
- Elimina del plugin los assets binarios corruptos para que no puedan volver a usarse por error.
- Añade una pestaña “Diseño PDF” para administradores.
- Carne, Pescado/Marisco y el logo oficial FRN se suben una sola vez a la Biblioteca de Medios de WordPress.
- Cada imagen se valida en servidor con MIME y getimagesize antes de guardarse.
- El PDF no se exporta si falta la foto correspondiente o el logo, evitando documentos comerciales incompletos.
- Las fotos del header se cargan como data URI desde uploads, evitando restricciones de rutas locales/chroot de Dompdf.
- La marca de agua usa el logo cargado y se pinta con el canvas de Dompdf en el centro de cada página con opacidad baja.
- Mantiene sin cambios tabla, orden alfabético, OFERTA, stock, precios y “Consultar precio”.

## 1.1.5 — PDF bloqueado al mockup aprobado

- El mockup aprobado pasa a ser la especificación visual del PDF.
- El header deja de usar el logo del tema de WordPress y deja de recomponer foto, logo y título con capas HTML.
- Usa cabeceras FRN prerenderizadas y optimizadas para Carne y Pescado/Marisco, con el logo oficial integrado.
- Solo la fecha permanece dinámica sobre la cabecera.
- Añade una marca de agua FRN centrada en todas las páginas mediante el canvas de Dompdf.
- El footer vuelve al formato de marca del mockup: FRN ATLÁNTICO + datos comerciales centrados.
- Código, producto y marca se validan como UTF-8 antes de renderizarse.
- Se confirma que textos como “GAMBON” proceden así del Excel origen; no se alteran nombres comerciales automáticamente.
- Mantiene sin cambios el badge OFERTA, orden alfabético, stock, precios y “Consultar precio”.

## 1.1.4 — Cabeceras fotográficas definitivas

- Usa exactamente las imágenes aportadas por FRN para Carne y Pescado/Marisco.
- Las fotografías se recortan previamente a proporción de cabecera y se optimizan para PDF.
- El header deja de estirar las imágenes y usa recorte proporcional tipo cover.
- Los dos fondos pesan aproximadamente 10 KB y 13 KB, evitando PDFs innecesariamente pesados.
- Reduce el footer de 11 pt a 8 pt para recuperar una presencia más equilibrada.
- Mantiene intactos tabla, orden alfabético, precios, stock, Consultar precio y badge OFERTA de la 1.1.3.

## 1.1.3 — Render PDF estable y alineado con el mockup

- Elimina las capas transparentes y z-index del PDF que podían producir artefactos visuales sobre códigos, stock y precios.
- Mantiene los datos reales intactos y refuerza anchuras/nowrap en Código, Stock y Precio.
- Sustituye el distintivo OFERTA por un badge vectorial rojo con llama blanca, embebido en el propio PDF.
- Mantiene OFERTA dentro de su posición alfabética; nunca reordena productos.
- El header fotográfico pasa a ser un fondo único sin capas superpuestas sobre la tabla.
- El branding FRN se repite en el footer de todas las páginas, sin una marca de agua flotante sobre el contenido.
- Añade numeración Página X de Y mediante el canvas de Dompdf, fuera del flujo HTML.
- Footer ampliado y centrado para mejorar lectura.
- Conserva Consultar precio cuando una referencia visible no tiene precio comercial.

## 1.1.2 — Trazabilidad semanal, cockpit ancho y PDF refinado

- Muestra el nombre del Excel seleccionado antes de previsualizar y confirma que está listo.
- Muestra de forma persistente el último Excel semanal publicado, fecha/hora, usuario y referencias activas.
- Al crear o editar una tarifa se muestra claramente el archivo semanal de origen.
- Ensancha el cockpit para aprovechar mejor pantallas grandes y evita saltos de línea en precios, euros, stock y costes.
- Mantiene los productos en orden alfabético independientemente de que estén marcados como OFERTA.
- OFERTA deja de alterar el orden y se muestra con un badge limpio sin estrella.
- Añade fondos fotográficos propios distintos para Carne y Pescado/Marisco en el header del PDF.
- Añade marca de agua FRN en todas las páginas del PDF.
- Aumenta la presencia de CARNE / PESCADO Y MARISCO y de la fecha en el encabezado.
- Aumenta dos puntos el footer del PDF y lo mantiene centrado.
- Conserva “Consultar precio” cuando una referencia visible no tiene precio comercial.

## 1.1.1 — Ajustes finales de PDF comercial

- Mantiene intacta la tipografía general del documento.
- Mejora exclusivamente el footer: centrado, más legible y ligeramente más grande.
- Reordena las columnas del PDF/CSV: Código, Producto, Marca, Coste promedio, Stock, Precio.
- Mantiene Precio siempre como última columna y destacado en negrita.
- Muestra “Consultar precio” cuando una referencia visible no tiene precio comercial.
- Mejora el distintivo OFERTA con una insignia visual más marcada y profesional.
- Mueve CARNE / PESCADO Y MARISCO y la fecha al bloque superior derecho del encabezado.
- Mantiene el resto del diseño, tamaños y jerarquía visual de la versión anterior.

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
