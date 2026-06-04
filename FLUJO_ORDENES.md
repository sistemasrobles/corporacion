# Diagrama de Flujo - Sistema de Órdenes

## PROGRAMACIÓN: URGENTE

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         FLUJO DE ÓRDENES URGENTE                            │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────┐
│ 0-BORRADOR  │  ◄─── JA crea la orden
└──────┬──────┘
       │
       ▼
┌──────────────────────┐
│ 1-ENVIADO_A_AREA     │  ◄─── JA envía a revisión
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ 2-REVISION_AREA      │  ◄─── AA revisa
└──────┬───────┬───────┘
       │       │
       │       └──────────► 5-OBSERVADO ──┐
       │                                   │
       ▼                                   │
┌──────────────────────┐                   │
│ 3-ORDEN_DE_COMPRA    │  ◄─── GA aprueba│
└──────┬───────────────┘                   │
       │                                   │
       │                      ┌────────────┘
       │                      │
       ▼                      ▼
   ┌─────────────────────────────────┐
   │   GF GENERA CRONOGRAMA DE PAGO  │
   │     (CREA CUOTAS 200)           │
   └──────────┬──────────────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ 102-CRONOGRAMA_DE_PAGO   │  ◄─── GF genera plan
   └──────────┬───────────────┘
              │
    ┌─────────┴──────────────┐
    │   CUOTAS EN PARALELO   │
    │ 200→201→202 (pagos)    │
    └──────────┬──────────────┘
              │
    ┌─────────▼──────────────────────────────┐
    │  CUANDO TODAS CUOTAS = 202             │
    │  (CONFIRMADO_ABONO)                    │
    │  Automático → Estado 55                │
    └─────────┬──────────────────────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ 55-TODAS_CUOTAS_PAGADAS  │
   └──────────┬───────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ AF VALIDA DOCUMENTOS     │
   │ Sube comprobante         │
   │ tributario               │
   └──────────┬───────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ 8-DOCUMENTOS_CONFORMES   │  ◄─── AF da conformidad
   └──────────┬───────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ UC1 INGRESA CÓDIGO       │
   │ DE REGISTRO              │
   └──────────┬───────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ 91-CODIGO_BANCO INGRESADO│  ◄─── UC1 ingresa código
   └──────────┬───────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ UC2 INGRESA CÓDIGO       │
   │ DE BANCO                 │
   └──────────┬───────────────┘
              │
              ▼
   ┌──────────────────────────┐
   │ 92-VISTO_BUENO_FINAL     │
   └──────────┬───────────────┘
              │
      ┌───────┴────────┐
      │                │
      ▼                ▼
  ┌─────────────┐  ┌──────────────┐
  │UC3 APRUEBA  │  │UC4 CIERRA    │
  │ (URGENTE)   │  │ (GENERAL)    │
  └─────────────┘  └──────────────┘


CUOTAS EN PARALELO (Estados 200-202)
────────────────────────────────────

200-PENDIENTE_POR_DEPOSITO
       │
       ▼
201-DEPOSITO_RECIBIDO
       │
       ▼
202-CONFIRMADO_ABONO

[AF puede "Observar" → 200] ◄─── Rebote/problema


ROLES Y ACCESO POR PROGRAMACIÓN
────────────────────────────────

URGENTE:
  JA: [1]
  AA: [1, 2, 5, 8, 55]
  GA: [2, 3, 4, 5, 100]
  GF: [3, 102, 200, 201, 202]
  AF: [8, 55, 200, 201, 202]
  UC1: [9, 91, 100]
  UC2: [55, 91, 92, 202]
  UC3: [92]
  UC4: [92]
  UC5: [101, 102]


ACCIONES ESPECIALES
───────────────────

AA - OBSERVAR (Estado 5):
  └─► Regresa a GA para revisión
  
AF - OBSERVAR ABONO:
  └─► Resetea cuota a 200
  └─► GF puede reintentar pago
  
UC2 - OBSERVAR (¿GENERAL?):
  └─► ¿ Regresa a GF/AF? [FALTA DEFINIR]
```

## PROGRAMACIÓN: GENERAL

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         FLUJO DE ÓRDENES GENERAL                            │
└─────────────────────────────────────────────────────────────────────────────┘

ESTRUCTURA SIMILAR A URGENTE, PERO:

├─ Estado 100 (GENERAL)  ◄─── GA aprueba en GENERAL
├─ Estado 101            ◄─── UC5
├─ Estado 102            ◄─── GF genera cronograma
│
├─ CUOTAS: 200→201→202
│
├─ Estado 55 (Todas cuotas pagadas)
├─ Estado 8 (AF conformidad)
├─ Estado 91 (UC1/UC2 código)
├─ Estado 92 (UC2 visto bueno → UC4 cierra)
│
└─ ¿UC2 OBSERVAR?: [FALTA DEFINIR DESTINO]


DIFERENCIAS GENERAL vs URGENTE
──────────────────────────────
• GENERAL: Mayor tiempo de procesamiento
• GENERAL: UC4 cierra (vs UC3 visto bueno en URGENTE)
• GENERAL: Políticas UC5 incluye [101, 102]
```

## MATRIZ DE TRANSICIONES

```
┌─────────────────┬──────────────┬─────────────────┐
│ ESTADO ACTUAL   │ ROL          │ SIGUIENTE       │
├─────────────────┼──────────────┼─────────────────┤
│ 0-BORRADOR      │ JA           │ 1               │
│ 1-ENVIADO       │ AA           │ 2 / 5           │
│ 2-REVISION      │ GA           │ 3 / 4 / 5 / 100 │
│ 3-ORDEN_COMPRA  │ GF           │ 102             │
│ 102-CRONOGRAMA  │ AF+CUOTAS    │ 55 (automático) │
│ 55-PAGADAS      │ AF           │ 8               │
│ 8-CONFORME      │ UC1          │ 91              │
│ 91-COD_REG      │ UC2          │ 92              │
│ 92-COD_BANCO    │ UC3/UC4      │ CIERRE          │
│ 5-OBSERVADO     │ AA→GA        │ 2 (revisión)    │
│ 200-PAGO_PEND   │ AF-observar  │ 200 (reintentar)│
└─────────────────┴──────────────┴─────────────────┘
```

## PENDIENTE DE DEFINICIÓN

```
❓ GENERAL - UC2 OBSERVAR:
   Cuando UC2 observa en GENERAL, ¿a quién va?
   - ¿A GF para revisar?
   - ¿A AF para resubir constancia?
   - ¿Otro destino?
```
