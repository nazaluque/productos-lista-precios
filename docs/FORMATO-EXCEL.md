# Formato del Excel

La primera hoja debe contener una cabecera y una fila por referencia.

| Columna | Obligatoria | Ejemplo |
|---|---:|---|
| Código | No | C00500 |
| Marca | No | FINEXCOR |
| Producto | Sí | BIFE ANCHO SIN TAPA |
| Stock | Sí | 524,18 |
| Precio | No | 25,90 |
| Oferta | No | Sí |

También se admiten los encabezados `Referencia`, `Nombre`, `Descripción`, `Cantidad` y `Destacado`.

El proceso siempre tiene dos pasos: **Previsualizar importación** y **Publicar en la landing**. Las filas con stock o precio inválido bloquean la publicación.
