# WooCommerce Factura.com

Plugin para integrar WooCommerce con Factura.com y generar CFDIs automáticamente.

## 🚀 Características

- ✅ **Generación automática de CFDIs** cuando se completa un pedido
- ✅ **Validación de RFC** en tiempo real durante el checkout
- ✅ **Modo demo** para pruebas sin consumir API
- ✅ **Soporte para Sandbox y Producción** de Factura.com
- ✅ **Envío automático por email** de CFDIs al cliente
- ✅ **Cancelación y regeneración** de CFDIs desde el admin
- ✅ **Configuración completa** según catálogos del SAT
- ✅ **Logs detallados** para debugging
- ✅ **Estadísticas** de CFDIs generados
- ✅ **Setup wizard** para configuración inicial
- ✅ **Compatible con HPOS** de WooCommerce

## 📋 Requisitos

- WordPress 5.0+
- WooCommerce 4.0+
- PHP 7.4+
- Extensiones PHP: `curl`, `json`, `mbstring`
- Cuenta en [Factura.com](https://factura.com)

## 🔧 Instalación

1. **Descarga** el plugin desde este repositorio
2. **Sube** el archivo ZIP a tu WordPress en `Plugins > Añadir nuevo > Subir plugin`
3. **Activa** el plugin
4. **Ejecuta** el asistente de configuración que aparecerá automáticamente

## ⚙️ Configuración

### Configuración Inicial

Al activar el plugin por primera vez, aparecerá un asistente que te guiará:

1. **Seleccionar entorno** (Demo, Sandbox, Producción)
2. **Agregar credenciales** de Factura.com (si no es modo demo)
3. **Configurar datos fiscales** básicos
4. **Probar conexión** con la API
5. **¡Listo para usar!**

### Obtener Credenciales de Factura.com

1. Inicia sesión en [factura.com](https://factura.com) o [sandbox.factura.com](https://sandbox.factura.com)
2. Ve a **Desarrolladores > API > Datos de acceso**
3. Copia tu `F-Api-Key` y `F-Secret-Key`
4. Ve a **Configuraciones > Series y folios** para obtener el `Serie ID`

### Configuración Manual

Si prefieres configurar manualmente:

1. Ve a **WooCommerce > Factura.com**
2. Configura tus credenciales en la pestaña **General**
3. Ajusta la configuración fiscal en **Configuración Fiscal**
4. Revisa opciones avanzadas en **Avanzado**

## 🎯 Uso

### Generación Automática

1. **Activa** "Generación Automática" en la configuración
2. Cuando un pedido cambie a **"Completado"**, se generará automáticamente el CFDI
3. El cliente recibirá un **email** con los archivos PDF y XML

### Generación Manual

1. Ve a **WooCommerce > Pedidos**
2. Abre cualquier pedido
3. En el meta box **"CFDI - Factura.com"**, haz clic en **"Generar CFDI"**

### Campo RFC en Checkout

- El plugin agrega automáticamente un campo **RFC** en el checkout
- Incluye **validación en tiempo real** del formato
- Si no se proporciona RFC, se usa el genérico `XAXX010101000`

## 🔄 Modo Demo

Perfecto para probar el plugin sin consumir tu API:

- **Activa** "Modo Demo" en la configuración
- Genera **CFDIs simulados** con UUIDs falsos
- **Prueba todas las funcionalidades** sin costo
- Cuando estés listo, cambia a modo real

## 📊 Estadísticas y Logs

### Panel de Estadísticas

Ve a **WooCommerce > Estadísticas CFDI** para ver:

- CFDIs generados por día/mes
- Tasas de éxito y cancelación
- Productos más facturados
- Clientes frecuentes
- Métricas de rendimiento

### Sistema de Logs

Ve a **WooCommerce > Logs Factura.com** para:

- Monitorear actividad del plugin
- Diagnosticar errores
- Filtrar por tipo y fecha
- Exportar logs para soporte

## 🛠️ Solución de Problemas

### Problemas Comunes

**Error: "API Key no configurada"**
- Verifica que hayas ingresado correctamente tu F-Api-Key en la configuración

**Error: "Serie ID no válida"**
- Asegúrate de usar el número de ID de la serie, no el nombre

**Los CFDIs no se generan automáticamente**
- Verifica que la "Generación Automática" esté activada
- Confirma que el pedido tenga estado "Completado"
- Revisa que el pedido tenga RFC (o permita genérico)

**El campo RFC no aparece en checkout**
- Ve a Configuración > General y verifica que no esté desactivado

### Debug Mode

1. Activa **"Modo Debug"** en Configuración > Avanzado
2. Reproduce el problema
3. Revisa los logs en **WooCommerce > Logs Factura.com**
4. Los logs detallados te ayudarán a identificar el problema

### Herramientas de Diagnóstico

En **Configuración > Herramientas** encontrarás:

- **Información del sistema**: Datos técnicos del servidor
- **Probar email**: Verificar configuración de correo
- **Probar conexión API**: Validar credenciales
- **Reparar instalación**: Corregir problemas de base de datos

## 🔒 Seguridad

- Las **credenciales** se almacenan de forma segura en la base de datos
- Se utilizan **nonces** para validar formularios
- **Sanitización** de todas las entradas de usuario
- **Validaciones** en servidor y cliente
- **Logs** no contienen información sensible

## 🤝 Contribuir

¡Las contribuciones son bienvenidas!

1. Fork el repositorio
2. Crea una rama para tu característica (`git checkout -b feature/nueva-caracteristica`)
3. Haz commit de tus cambios (`git commit -am 'Agrega nueva característica'`)
4. Push a la rama (`git push origin feature/nueva-caracteristica`)
5. Abre un Pull Request

## 📝 Changelog

### v1.0.0 (2024-01-15)

**🎉 Lanzamiento inicial**

- Integración completa con API de Factura.com
- Generación automática y manual de CFDIs
- Modo demo para pruebas
- Setup wizard para configuración inicial
- Campo RFC con validación en checkout
- Panel de estadísticas y logs
- Soporte para sandbox y producción
- Envío automático por email
- Cancelación y regeneración de CFDIs
- Compatible con WooCommerce HPOS

## 📄 Licencia

Este plugin está licenciado bajo la [GPL v2 o posterior](https://www.gnu.org/licenses/gpl-2.0.html).

## 🆘 Soporte

- **Documentación**: Revisa este README y los comentarios en el código
- **Issues**: Abre un issue en este repositorio
- **Factura.com**: Para problemas específicos de la API, contacta a soporte@factura.com

## ⚖️ Disclaimer

Este plugin es desarrollado de forma independiente y no está oficialmente asociado con Factura.com. Es tu responsabilidad cumplir con las regulaciones fiscales mexicanas y validar que los CFDIs generados sean correctos.

---

**¿Te gusta el plugin?** ⭐ ¡Dale una estrella al repositorio!

**¿Encontraste un bug?** 🐛 Abre un issue con todos los detalles.

**¿Quieres una nueva característica?** 💡 Compártela en los issues.