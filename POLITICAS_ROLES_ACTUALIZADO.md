# POLÍTICAS DE ROLES - ACTUALIZADO

## PROGRAMACIÓN URGENTE

| Rol | Estados Permitidos | Descripción |
|-----|-------------------|------------|
| **JA** | [1] | Crea orden |
| **AA** | [2, 5, 8, 202] | Completa, corrige observaciones, sube comprobante, verifica abonos |
| **GA** | [2, 3, 4, 5] | Revisa, aprueba, rechaza, observa |
| **GF** | [3, 102, 200, 201] | Aprueba cronograma, realiza abonos |
| **AF** | [9, 200, 201, 202] | Valida documentos (1ra vez), sube constancia |
| **UC1** | [91] | Ingresa código de registro |
| **UC2** | [92] | Ingresa código de banco |
| **UC3** | [10] | Visto bueno final |

---

## PROGRAMACIÓN GENERAL

| Rol | Estados Permitidos | Descripción |
|-----|-------------------|------------|
| **JA** | [1] | Crea orden |
| **AA** | [2, 5, 8, 55, 200, 201, 202] | Completa, corrige observaciones, sube comprobante, verifica abonos |
| **GA** | [2, 4, 5] | Revisa, rechaza, observa (NO aprueba en GENERAL) |
| **UC1** | [100, 91] | Revisa documentos contables, ingresa código registro |
| **UC3** | [91, 101] | Valida, ingresa código (UC1 lo pasó) |
| **UC5** | [101, 102] | Confirma y genera cronograma |
| **GF** | [102, 200, 201] | Realiza abonos |
| **AF** | [102, 200, 201, 202] | Sube constancia de pago |
| **UC2** | [55, 92] | Verifica abonos, visto bueno |
| **UC4** | [92, 10] | Cierre final |

---

## MATRIZ COMBINADA

Para implementar en orders_politicas, combinar ambos flujos:

| user_type | status_id | Flujo |
|-----------|-----------|-------|
| JA | 1 | URGENTE, GENERAL |
| AA | 2, 5, 8, 55, 200, 201, 202 | URGENTE, GENERAL |
| GA | 2, 3, 4, 5 | URGENTE |
| GA | 2, 4, 5 | GENERAL |
| GF | 3, 102, 200, 201 | URGENTE |
| GF | 102, 200, 201 | GENERAL |
| AF | 9, 200, 201, 202 | URGENTE |
| AF | 102, 200, 201, 202 | GENERAL |
| UC1 | 91 | URGENTE |
| UC1 | 100, 91 | GENERAL |
| UC2 | 92 | URGENTE |
| UC2 | 55, 92 | GENERAL |
| UC3 | 10 | URGENTE |
| UC3 | 91, 101 | GENERAL |
| UC4 | 92, 10 | GENERAL |
| UC5 | 101, 102 | GENERAL |

---

## NOTAS IMPORTANTES

- Los estados [200, 201, 202, 55] son **independientes por programación**
- AA ve ambas programaciones en bloque de abonos
- GF ve ambas programaciones en bloque de abonos
- AF ve ambas programaciones en bloque de abonos
- UC1, UC2, UC3, UC4, UC5 son **específicos por programación**
- Estado [5] OBSERVADO es transversal en ambos flujos
