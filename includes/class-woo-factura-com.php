// ===============================================
// EJEMPLOS DE INTEGRACIÓN DESDE TU POS ACTUAL
// ===============================================

// 1. GENERAR CFDI DESDE TU POS
async function generarCFDIDesdePos(ventaData) {
    const payload = {
        order_data: {
            items: [
                {
                    name: "Producto A",
                    price: 100.00,
                    quantity: 2,
                    sku: "PROD-001"
                },
                {
                    name: "Producto B", 
                    price: 50.00,
                    quantity: 1,
                    sku: "PROD-002"
                }
            ]
        },
        customer_data: {
            first_name: "Juan",
            last_name: "Pérez",
            email: "juan@ejemplo.com",
            phone: "5551234567",
            rfc: "PERJ800101AAA" // Opcional
        }
    };

    try {
        const response = await fetch('https://tu-tienda.com/wp-json/woo-factura-com/v1/cfdi/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer TU_TOKEN_API'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        
        if (result.success) {
            console.log('CFDI generado:', result.cfdi.uuid);
            console.log('PDF:', result.cfdi.pdf_url);
            console.log('XML:', result.cfdi.xml_url);
            
            // Mostrar en tu POS
            mostrarCFDIEnPOS(result.cfdi);
            
            // Opcional: Imprimir automáticamente
            if (confirm('¿Imprimir CFDI?')) {
                imprimirCFDI(result.cfdi.uuid);
            }
        } else {
            console.error('Error:', result.message);
            alert('Error generando CFDI: ' + result.message);
        }
        
    } catch (error) {
        console.error('Error de conexión:', error);
        alert('Error de conexión con el servidor de CFDIs');
    }
}

// 2. VERIFICAR ESTADO DE CFDI
async function verificarCFDI(orderId) {
    try {
        const response = await fetch(`https://tu-tienda.com/wp-json/woo-factura-com/v1/cfdi/status/${orderId}`, {
            headers: {
                'Authorization': 'Bearer TU_TOKEN_API'
            }
        });

        const result = await response.json();
        
        if (result.has_cfdi) {
            return {
                existe: true,
                uuid: result.uuid,
                pdf: result.pdf_url,
                xml: result.xml_url
            };
        } else {
            return { existe: false };
        }
        
    } catch (error) {
        console.error('Error verificando CFDI:', error);
        return { existe: false, error: error.message };
    }
}

// 3. IMPRIMIR CFDI
async function imprimirCFDI(uuid) {
    try {
        const response = await fetch(`https://tu-tienda.com/wp-json/woo-factura-com/v1/cfdi/print/${uuid}`, {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer TU_TOKEN_API'
            }
        });

        const result = await response.json();
        
        if (result.success) {
            console.log('CFDI enviado a impresión');
            // Aquí puedes integrar con tu impresora local
            enviarAImpresoraLocal(result.pdf_url);
        }
        
    } catch (error) {
        console.error('Error imprimiendo:', error);
    }
}

// ===============================================
// INTEGRACIÓN CON SISTEMAS POS POPULARES
// ===============================================

// EJEMPLO 1: POS en PHP
class CFDIIntegration {
    private $api_url = 'https://tu-tienda.com/wp-json/woo-factura-com/v1';
    private $token = 'TU_TOKEN_API';
    
    public function generarCFDI($venta) {
        $data = [
            'order_data' => [
                'items' => $venta['productos']
            ],
            'customer_data' => [
                'first_name' => $venta['cliente']['nombre'],
                'email' => $venta['cliente']['email'],
                'rfc' => $venta['cliente']['rfc'] ?? null
            ]
        ];
        
        $response = $this->makeRequest('/cfdi/create', $data);
        return $response;
    }
    
    private function makeRequest($endpoint, $data) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token
            ]
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }
}

// EJEMPLO 2: POS en Python
import requests
import json

class CFDIIntegrator:
    def __init__(self, api_url, token):
        self.api_url = api_url
        self.token = token
        self.headers = {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {token}'
        }
    
    def generar_cfdi(self, venta_data):
        payload = {
            'order_data': {
                'items': venta_data['productos']
            },
            'customer_data': {
                'first_name': venta_data['cliente']['nombre'],
                'email': venta_data['cliente']['email'],
                'rfc': venta_data['cliente'].get('rfc')
            }
        }
        
        response = requests.post(
            f'{self.api_url}/cfdi/create',
            headers=self.headers,
            json=payload
        )
        
        return response.json()

# Uso:
# cfdi = CFDIIntegrator('https://tu-tienda.com/wp-json/woo-factura-com/v1', 'TU_TOKEN')
# resultado = cfdi.generar_cfdi(datos_venta)

// ===============================================
// INTEGRACIÓN CON IFRAME (MÁS SIMPLE)
// ===============================================

// Si prefieres algo más visual, puedes embeber la interfaz web
function mostrarInterfazCFDI(orderId) {
    const iframe = document.createElement('iframe');
    iframe.src = `https://tu-tienda.com/wp-admin/post.php?post=${orderId}&action=edit`;
    iframe.style.width = '800px';
    iframe.style.height = '600px';
    
    // Mostrar en modal en tu POS
    const modal = document.getElementById('modal-cfdi');
    modal.appendChild(iframe);
    modal.style.display = 'block';
}

// ===============================================
// WEBHOOK PARA NOTIFICACIONES AUTOMÁTICAS
// ===============================================

// En tu servidor POS, recibir notificaciones cuando se genere un CFDI
app.post('/webhook/cfdi-generado', (req, res) => {
    const { order_id, uuid, pdf_url, xml_url } = req.body;
    
    console.log(`CFDI generado para pedido ${order_id}: ${uuid}`);
    
    // Actualizar tu base de datos local
    database.updateOrder(order_id, {
        cfdi_uuid: uuid,
        cfdi_pdf: pdf_url,
        cfdi_xml: xml_url,
        cfdi_generated_at: new Date()
    });
    
    // Opcional: Imprimir automáticamente
    printManager.printCFDI(pdf_url);
    
    res.status(200).send('OK');
});