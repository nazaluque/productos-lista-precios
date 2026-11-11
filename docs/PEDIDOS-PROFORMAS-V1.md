# FRN · Pedidos / Proformas v1

## Alcance

Módulo interno dentro de la herramienta privada `/stock/` de frnatlantico.es.

No crea otra aplicación, otro login ni otro catálogo.

La fuente de verdad de producto sigue siendo el maestro FRN actual:
- código
- marca
- producto
- familia
- grupo comercial
- stock
- unidad
- precio origen
- precio comercial
- coste
- traducciones

## Navegación propuesta

Una única herramienta:

1. Datos semanales
2. Crear PDF
3. Pedidos / Proformas
4. Clientes
5. Traducciones
6. Diseño PDF
7. Usuarios
8. Configuración

## Dos documentos desde el mismo motor

### Pedido interno
Pensado para pasar operaciones cerradas a la persona que carga/prepara el pedido.

Puede enviarse a una lista interna predeterminada de correos.

### Proforma / Presupuesto
Pensado para enviar al cliente.

Puede enviarse al email del cliente o a otra dirección escrita en el momento.

Los dos comparten:
- cliente
- productos
- cantidades
- precios
- observaciones
- PDF FRN
- histórico

## Cliente

Datos mínimos:
- razón social / nombre
- CIF/NIF/VAT
- contacto
- teléfono
- email
- dirección fiscal
- código postal
- población
- provincia
- país
- dirección de entrega
- CP entrega
- población entrega
- provincia entrega
- país entrega
- observaciones

Funcionalidad:
- búsqueda rápida
- autocompletado
- últimos clientes
- duplicar dirección fiscal en entrega
- varias direcciones de entrega en una fase posterior

## Líneas de producto

Los productos se buscan en el maestro existente.

Al añadir una referencia se copia al pedido un snapshot de:
- ID origen
- código
- marca
- nombre
- unidad
- precio comercial vigente
- stock de referencia

El precio queda editable antes de guardar.

Después de guardar el pedido, el precio histórico no cambia aunque cambie el maestro.

## Cantidades y packaging

Cada línea permite:
- cantidad
- unidad de venta: kg / cajas / pallets / unidades
- número de cajas
- número de pallets
- kg totales
- precio
- observación de línea

El maestro de producto se ampliará con datos persistentes no sobrescritos por la importación semanal:
- kg por caja
- unidades por caja
- cajas por pallet
- unidad habitual de venta

Las conversiones son ayudas, no bloqueos.
El usuario puede corregir los kilos reales.

## Estado del documento

Estados:
- borrador
- listo
- enviado
- anulado

No se elimina histórico de documentos enviados.

## Numeración

Formato propuesto:
- PED-2026-000001
- PRO-2026-000001

Secuencias separadas.

## PDF

A4 limpio FRN.

Cabecera:
- logo FRN oficial de la Biblioteca de Medios
- PEDIDO / PROFORMA
- número
- fecha
- comercial

Bloque cliente:
- empresa
- CIF
- contacto
- teléfono
- email

Bloque entrega:
- dirección
- población
- provincia
- país
- fecha solicitada
- horario / observaciones

Tabla:
- Código
- Producto
- Marca
- Cantidad
- Presentación
- Kg
- Precio
- Importe

Pie:
- observaciones
- datos FRN
- usuario creador
- fecha/hora

## Email

Configuración:
- remitente visible
- reply-to
- destinatarios internos por defecto
- CC opcional
- asunto base

Pedido interno:
- destinatarios internos predeterminados
- posibilidad de añadir/quitar destinatarios antes de enviar

Proforma:
- email del cliente precargado
- editable
- CC opcional

Adjuntos:
- PDF generado

Registro:
- fecha/hora
- usuario
- destinatarios
- asunto
- resultado wp_mail

## Seguridad

- herramienta privada existente
- nonce en todas las acciones
- capabilities nuevas:
  - frn_manage_orders
  - frn_send_orders
  - frn_manage_customers
  - frn_manage_order_settings
- sanitización server-side
- noindex
- no cache

## Tablas nuevas

### wp_frn_customers
Cliente y datos de contacto.

### wp_frn_orders
Cabecera del documento.

### wp_frn_order_items
Snapshot de líneas.

### wp_frn_order_log
Histórico de eventos:
- creado
- editado
- PDF generado
- enviado
- anulado

## Mobile first

En móvil:
- cliente en tarjetas
- buscador de producto tipo autocomplete
- línea de producto en card
- controles grandes
- barra inferior fija con Guardar / PDF / Enviar

No usar tabla ancha como interfaz principal móvil.

## Fases

### Fase 1
- clientes
- pedidos/proformas
- selector de productos
- cantidades
- precio
- guardar
- histórico

### Fase 2
- PDF
- email
- destinatarios predeterminados
- registro de envío

### Fase 3
- packaging automático
- duplicar pedido
- filtros
- búsqueda avanzada
- auditoría y permisos

## Regla crítica

Nunca duplicar el catálogo.
Los pedidos leen el maestro FRN actual y guardan snapshots históricos por documento.
