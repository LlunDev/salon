# Roadmap del MVP de reservas

El objetivo del MVP es que un cliente pueda escoger servicios, reservar una cita disponible y recibir un recordatorio. La disponibilidad pertenece al salón: el cliente no selecciona profesional y la cita no se asigna a miembros del staff.

## Estado actual

- [x] Gestión completa de servicios: listado, búsqueda, creación, edición y eliminación.
- [x] Duración, precio, disponibilidad e imagen por servicio.
- [x] Configuración de horarios del salón.
- [ ] Reserva pública de citas.
- [ ] Agenda administrativa.
- [ ] Recordatorios.

## Orden de implementación

### 1. Diseñar el dominio de citas [completado]

Definir el modelo y sus reglas antes de construir la interfaz.

- Datos de la cita y relación con el tenant.
- Uno o varios servicios por cita.
- Datos mínimos del cliente.
- Fecha de inicio, fecha de finalización, duración y precio total.
- Estados de la cita y transiciones permitidas.
- Reglas para cancelar.
- Protección contra reservas solapadas.

#### Decisiones iniciales del dominio

- Estados del MVP: `confirmed`, `cancelled` y `completed`.
- La cita pasa directamente a `confirmed` cuando el cliente completa correctamente la reserva y, desde ese momento, consume capacidad del salón.
- Una cita `cancelled` o `completed` deja de consumir capacidad.
- El salón configura, como un número entero de horas antes del inicio, la antelación mínima con la que puede cancelar el cliente. El valor predeterminado para salones nuevos es de 24 horas. El salón puede cancelar una cita en cualquier momento.
- No existe reprogramación en el MVP. Para cambiar una cita se cancela la reserva actual y el cliente realiza nuevamente el flujo completo de reserva.
- Cuando el salón cancela, puede registrar un motivo y optar por enviarlo al cliente mediante el canal de comunicaciones utilizado para los recordatorios.
- El salón marca la cita como `completed`. Permitir que el cliente confirme la finalización y escriba una reseña queda fuera del MVP.
- Los solapamientos se permiten hasta alcanzar la capacidad concurrente configurada para el salón, expresada como un único número entero general que no varía por día ni franja horaria.

**Criterio de salida:** el dominio, esquema de datos, contratos API y casos de prueba principales están definidos.

### 2. Configurar horarios del salón [completado]

Crear la disponibilidad general usada por todas las reservas.

- Horario semanal de apertura y cierre.
- Días cerrados.
- Bloqueos excepcionales por fecha y rango horario.
- Zona horaria del salón.
- Intervalo utilizado para proponer horas de inicio.

**Criterio de salida:** el administrador puede configurar la disponibilidad y el backend puede determinar si un rango está abierto o bloqueado.

### 3. Seleccionar servicios en la reserva pública

Construir el inicio del flujo mobile-first para clientes.

- Catálogo público con servicios disponibles.
- Selección de uno o varios servicios.
- Resumen persistente de la selección.
- Cálculo de duración y precio total.

**Criterio de salida:** el cliente puede construir una selección válida y continuar al calendario.

### 4. Calcular y mostrar horarios disponibles

Generar slots desde la disponibilidad real del salón.

- Combinar duración total y horario del salón.
- Excluir bloqueos y citas existentes.
- No ofrecer horarios que terminen después del cierre.
- Consultar disponibilidad nuevamente antes de confirmar.
- Manejar correctamente zona horaria y cambios de fecha.

**Criterio de salida:** el cliente solo puede seleccionar horarios que siguen disponibles para la duración completa de su reserva.

### 5. Registrar o autenticar al cliente

Exigir una cuenta de cliente antes de confirmar la cita y solicitar únicamente la información necesaria para gestionarla y enviar recordatorios.

- Nombre.
- Teléfono.
- Correo electrónico obligatorio.
- Registro e inicio de sesión mediante correo electrónico y contraseña.
- Asociación de la reserva con la cuenta autenticada.
- La cuenta del cliente pertenece a un único salón. La misma persona debe registrarse por separado en cada salón y puede reutilizar el mismo correo en tenants diferentes.

**Criterio de salida:** cada reserva queda asociada con una cuenta de cliente autenticada y con datos de contacto válidos.

### 6. Crear y confirmar la cita

Cerrar el flujo público de reserva de forma segura.

- Validación final de servicios, importes y disponibilidad en el backend.
- Prevención de reservas concurrentes sobre el mismo horario.
- Persistencia de los valores históricos de servicios, duración y precio.
- Pantalla de confirmación con resumen de la cita.
- Toasts y estados de error recuperables.

**Criterio de salida:** una reserva confirmada aparece en el sistema una sola vez y conserva los datos comerciales vigentes al momento de reservar.

### 7. Implementar la agenda administrativa

Permitir que el salón gestione las citas recibidas.

- Vista mobile-first por día.
- Navegación entre fechas.
- Detalle de cita, servicios y cliente.
- Creación manual de citas.
- Cancelación y actualización de estado.
- Bloqueos manuales de disponibilidad.

**Criterio de salida:** el salón puede operar su jornada desde el teléfono sin depender de herramientas externas.

### 8. Enviar recordatorios

Automatizar comunicaciones sobre citas confirmadas.

- Canal inicial definido por producto.
- Momento configurable o regla inicial fija.
- Jobs en cola y reintentos controlados.
- Registro de envíos, fallos y motivo de omisión.
- Cancelación del recordatorio al cancelar una cita.
- Comunicación opcional del motivo cuando el salón cancela una cita.

**Criterio de salida:** las citas elegibles generan un único recordatorio trazable y los fallos pueden diagnosticarse.

## Validación final del MVP

- [ ] El flujo público funciona completamente desde un celular.
- [ ] El cliente puede registrarse, autenticarse y reservar sin seleccionar profesional.
- [ ] No se pueden confirmar citas fuera del horario del salón.
- [ ] No se pueden confirmar citas que superen la capacidad concurrente del salón.
- [ ] La agenda refleja inmediatamente las reservas públicas y manuales.
- [ ] Las cancelaciones actualizan la disponibilidad y una nueva fecha requiere una reserva nueva.
- [ ] Los recordatorios corresponden al estado y horario actual de la cita.
- [ ] Todos los datos están aislados por tenant.

## Fuera del MVP

- Selección o asignación de profesionales.
- Horarios y disponibilidad por profesional.
- Comisiones del staff.
- Pagos online.
- Lista de espera.
- Programas de fidelización.
- Reservas recurrentes.
- Reseñas de clientes.
- Confirmación de servicio completado por parte del cliente.
- Aplicación móvil nativa.

Estas capacidades solo deben incorporarse después de validar el flujo principal de reservas y no deben condicionar el diseño de la primera versión.

## Próxima tarea

Implementar la fase 3: catálogo público y selección persistente de uno o varios servicios con cálculo de duración y precio total.
