// config/moneda.js - Helper JavaScript para mostrar precios en USD y Bs.
// Debe cargarse en todas las vistas que muestren precios.

const Moneda = {
    tasa: 1.00,
    cargada: false,
    
    /**
     * Carga la tasa de cambio actual desde el servidor.
     * Se ejecuta al inicio de cada página.
     */
    async cargarTasa() {
        try {
            const res = await fetch('/iaa_iuty/controllers/get_tasa_actual.php');
            const data = await res.json();
            if (data.success && data.tasa) {
                this.tasa = parseFloat(data.tasa);
                this.cargada = true;
                console.log('Tasa de cambio cargada:', this.tasa);
            }
        } catch (err) {
            console.warn('No se pudo cargar la tasa, usando 1.00', err);
            this.tasa = 1.00;
        }
        return this.tasa;
    },
    
    /**
     * Convierte USD a BS usando la tasa cargada.
     */
    usdABsf(montoUsd) {
        return parseFloat(montoUsd || 0) * this.tasa;
    },
    
    /**
     * Formatea un precio en USD (ej: $25.00).
     */
    fmtUsd(montoUsd) {
        return '$' + parseFloat(montoUsd || 0).toFixed(2);
    },
    
    /**
     * Formatea un precio en Bs. (ej: Bs. 1250.00).
     */
    fmtBsf(montoUsd) {
        const bsf = this.usdABsf(montoUsd);
        return 'Bs. ' + bsf.toLocaleString('es-VE', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    },
    
    /**
     * Formatea un precio en ambos formatos (ej: $25.00 / Bs. 1250.00).
     */
    fmtDual(montoUsd) {
        return `${this.fmtUsd(montoUsd)} / ${this.fmtBsf(montoUsd)}`;
    },
    
    /**
     * Devuelve HTML con el precio en ambos formatos.
     * Útil para insertar directamente en tablas.
     */
    htmlDual(montoUsd) {
        const usd = this.fmtUsd(montoUsd);
        const bsf = this.fmtBsf(montoUsd);
        return `<span class="precio-dual">
                    <span class="precio-usd">${usd}</span>
                    <span class="separador"> / </span>
                    <span class="precio-bsf">${bsf}</span>
                </span>`;
    },
    
    /**
     * Obtiene la tasa actual (para mostrar en la UI si es necesario).
     */
    getTasa() {
        return this.tasa;
    },
    
    /**
     * Verifica si la tasa ya fue cargada.
     */
    estaCargada() {
        return this.cargada;
    }
};

// Auto-cargar la tasa al cargar la página
document.addEventListener('DOMContentLoaded', () => {
    Moneda.cargarTasa();
});

// Estilos para los precios duales (agregar al CSS si es necesario)
const estiloMoneda = document.createElement('style');
estiloMoneda.textContent = `
    .precio-dual {
        display: inline-flex;
        align-items: baseline;
        gap: 0.25rem;
    }
    .precio-usd {
        font-weight: 600;
        color: #28a745;
    }
    .precio-bsf {
        font-weight: 400;
        color: #6c757d;
        font-size: 0.9em;
    }
    .separador {
        color: #adb5bd;
    }
`;
document.head.appendChild(estiloMoneda);