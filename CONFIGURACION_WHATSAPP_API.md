# Guía de Configuración de WhatsApp Business API

## 📋 Resumen de Requisitos

### 1. Requisitos Previos

#### Cuenta de Meta Business
- ✅ Cuenta de Facebook Business Manager activa
- ✅ WhatsApp Business Account (WABA) creada
- ✅ Número de teléfono verificado para WhatsApp Business

#### Aplicación en Meta for Developers
- ✅ Aplicación creada en [Meta for Developers](https://developers.facebook.com/)
- ✅ Producto "WhatsApp" agregado a la aplicación
- ✅ Permisos de WhatsApp Business API configurados

### 2. Credenciales Necesarias

#### Credenciales Obligatorias

1. **Phone Number ID**
   - ID único del número de teléfono de WhatsApp Business
   - Se obtiene desde Meta Business Manager → WhatsApp → Configuración
   - Formato: Número largo (ej: `123456789012345`)

2. **Access Token**
   - Token de acceso permanente o temporal
   - Se genera desde Meta for Developers → Tu App → WhatsApp → Configuración API
   - Formato: Cadena larga que comienza con `EAA...`
   - ⚠️ **Importante**: Los tokens temporales expiran. Usa tokens permanentes para producción.

3. **Verify Token**
   - Token personalizado para verificar el webhook
   - Lo defines tú mismo (puede ser cualquier string seguro)
   - Se usa para verificar que las peticiones al webhook vienen de Meta
   - Ejemplo: `mi_token_secreto_12345`

#### Credenciales Opcionales (Recomendadas)

4. **App Secret**
   - Secreto de la aplicación de Meta
   - Se obtiene desde Meta for Developers → Tu App → Configuración → Básico
   - Se usa para verificar la firma de los webhooks (seguridad adicional)
   - ⚠️ **Importante**: Mantén este valor seguro, nunca lo expongas públicamente

5. **Business ID (WABA ID)**
   - ID de la cuenta de WhatsApp Business
   - Se obtiene automáticamente o desde Meta Business Manager
   - Útil para operaciones a nivel de cuenta

### 3. Configuración de Webhook

#### URL del Webhook
- **URL requerida**: `https://tu-dominio.com/api/webhook/handle`
- Debe ser accesible desde internet (HTTPS obligatorio)
- Meta enviará eventos a esta URL

#### Eventos a Suscribir
En Meta for Developers, configura el webhook para recibir:
- ✅ `messages` - Mensajes entrantes y salientes
- ✅ `message_status` - Estados de mensajes (enviado, entregado, leído)
- ✅ `message_template_status_update` - Estados de plantillas

#### Verificación del Webhook
1. Meta enviará una petición GET a tu webhook con:
   - `hub.mode=subscribe`
   - `hub.verify_token=tu_verify_token`
   - `hub.challenge=string_aleatorio`

2. Tu servidor debe:
   - Verificar que `hub.verify_token` coincida con tu `WHATSAPP_VERIFY_TOKEN`
   - Responder con el valor de `hub.challenge`

### 4. Configuración en el Sistema

#### Opción 1: Desde el Panel (Recomendado)
1. Accede a `/whatsapp/settings` (requiere rol de administrador)
2. Completa los campos:
   - Phone Number ID
   - Access Token
   - Verify Token
   - App Secret (opcional pero recomendado)
   - API Version (por defecto: `v18.0`)
   - Base URL (por defecto: `https://graph.facebook.com`)
   - Business ID (opcional)
3. Haz clic en "Guardar Configuración"
4. Prueba la conexión desde `/whatsapp/test-connection`

#### Opción 2: Desde archivo .env (Fallback)
Si no configuras desde el panel, el sistema usará los valores del `.env`:

```env
WHATSAPP_PHONE_NUMBER_ID=tu_phone_number_id
WHATSAPP_ACCESS_TOKEN=tu_access_token
WHATSAPP_VERIFY_TOKEN=tu_verify_token
WHATSAPP_APP_SECRET=tu_app_secret
WHATSAPP_API_VERSION=v18.0
WHATSAPP_API_BASE_URL=https://graph.facebook.com
WHATSAPP_BUSINESS_ID=tu_business_id
```

**Nota**: Los valores configurados desde el panel tienen prioridad sobre el `.env`.

### 5. Pasos de Configuración Detallados

#### Paso 1: Crear Aplicación en Meta for Developers
1. Ve a [Meta for Developers](https://developers.facebook.com/)
2. Crea una nueva aplicación o selecciona una existente
3. Agrega el producto "WhatsApp"
4. Configura la aplicación según tus necesidades

#### Paso 2: Obtener Phone Number ID
1. Ve a Meta Business Manager
2. Navega a WhatsApp → Configuración
3. Copia el "Phone Number ID" de tu número de WhatsApp Business

#### Paso 3: Generar Access Token
1. En Meta for Developers → Tu App → WhatsApp → Configuración API
2. Genera un token de acceso
3. Para producción, crea un token permanente:
   - Ve a Sistema → Tokens de acceso
   - Crea un token con permisos de WhatsApp Business Management API
   - Selecciona "Nunca expira" (si es posible)

#### Paso 4: Configurar Webhook
1. En Meta for Developers → Tu App → WhatsApp → Configuración
2. En "Webhook", haz clic en "Configurar webhook"
3. Ingresa:
   - **URL de devolución de llamada**: `https://tu-dominio.com/api/webhook/handle`
   - **Token de verificación**: El mismo que configuraste en `WHATSAPP_VERIFY_TOKEN`
4. Suscribe los eventos necesarios
5. Haz clic en "Verificar y guardar"

#### Paso 5: Obtener App Secret
1. En Meta for Developers → Tu App → Configuración → Básico
2. Copia el "Secreto de la aplicación"
3. ⚠️ **Importante**: Si no lo ves, haz clic en "Mostrar" (puede requerir verificación)

#### Paso 6: Configurar en el Sistema
1. Accede al panel de administración
2. Ve a Configuración de WhatsApp
3. Ingresa todas las credenciales
4. Guarda la configuración
5. Prueba la conexión

### 6. Verificación y Pruebas

#### Prueba de Conexión
1. Ve a `/whatsapp/test-connection`
2. Haz clic en "Probar Conexión"
3. El sistema verificará:
   - ✅ Credenciales configuradas
   - ✅ Conexión con WhatsApp API
   - ✅ Información del número de teléfono
   - ✅ Estado del webhook

#### Prueba del Webhook
1. Desde el panel, haz clic en "Re-verificar" en la sección de Webhook
2. El sistema simulará la verificación de Meta
3. Si es exitoso, verás un mensaje de confirmación

### 7. Límites y Consideraciones

#### Límites de la API
- **Mensajes gratuitos**: 1,000 conversaciones gratuitas al mes
- **Ventana de 24 horas**: Puedes responder mensajes gratuitamente dentro de 24 horas
- **Plantillas**: Requieren aprobación de Meta antes de usar
- **Rate Limits**: Consulta la documentación oficial para límites de velocidad

#### Mejores Prácticas
- ✅ Usa tokens permanentes en producción
- ✅ Mantén el App Secret seguro
- ✅ Verifica las firmas de los webhooks
- ✅ Implementa manejo de errores
- ✅ Monitorea los límites de uso
- ✅ Usa HTTPS para el webhook (obligatorio)

### 8. Solución de Problemas Comunes

#### Error: "Phone Number ID and Access Token must be configured"
- **Solución**: Verifica que hayas configurado ambos valores en el panel o `.env`

#### Error: "Invalid OAuth access token"
- **Solución**: 
  - Verifica que el Access Token sea válido
  - Regenera el token si ha expirado
  - Asegúrate de que el token tenga los permisos correctos

#### Error: "Webhook verification failed"
- **Solución**:
  - Verifica que el Verify Token coincida exactamente
  - Asegúrate de que la URL del webhook sea accesible desde internet
  - Verifica que uses HTTPS

#### Error: "Phone number not found"
- **Solución**:
  - Verifica que el Phone Number ID sea correcto
  - Asegúrate de que el número esté verificado en Meta Business Manager

### 9. Recursos Adicionales

#### Documentación Oficial
- [WhatsApp Business API Documentation](https://developers.facebook.com/docs/whatsapp)
- [Meta for Developers](https://developers.facebook.com/)
- [Webhook Setup Guide](https://developers.facebook.com/docs/graph-api/webhooks)

#### Herramientas Útiles
- [Graph API Explorer](https://developers.facebook.com/tools/explorer/) - Para probar llamadas a la API
- [Webhook Tester](https://webhook.site/) - Para probar webhooks localmente

### 10. Checklist de Configuración

- [ ] Cuenta de Meta Business Manager creada
- [ ] WhatsApp Business Account (WABA) configurada
- [ ] Aplicación creada en Meta for Developers
- [ ] Producto WhatsApp agregado a la aplicación
- [ ] Phone Number ID obtenido
- [ ] Access Token generado (preferiblemente permanente)
- [ ] Verify Token definido
- [ ] App Secret obtenido
- [ ] Webhook configurado en Meta for Developers
- [ ] URL del webhook accesible desde internet (HTTPS)
- [ ] Eventos del webhook suscritos
- [ ] Credenciales configuradas en el panel o `.env`
- [ ] Prueba de conexión exitosa
- [ ] Webhook verificado correctamente

---

## 📝 Notas Importantes

1. **Seguridad**: Nunca compartas tus credenciales públicamente. Los tokens y secretos son sensibles.

2. **Producción vs Desarrollo**: 
   - En desarrollo, puedes usar tokens temporales
   - En producción, usa tokens permanentes y configura correctamente el App Secret

3. **Actualización de Tokens**: Si necesitas actualizar un token, puedes hacerlo desde el panel sin necesidad de modificar el `.env`.

4. **Prioridad de Configuración**: 
   - Base de datos (panel) > `.env` > Valores por defecto

5. **Cache**: Las configuraciones se cachean por 1 hora. Si actualizas valores, espera unos minutos o limpia el cache.

---

**Última actualización**: Diciembre 2024
