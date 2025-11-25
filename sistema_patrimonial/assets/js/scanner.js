class ScannerManager {
    constructor() {
        this.codeReader = null;
        this.isScanning = false;
        this.selectedDeviceId = null;
    }

    async initScanner() {
        try {
            const { BrowserMultiFormatReader } = ZXing;
            this.codeReader = new BrowserMultiFormatReader();
            
            const videoInputDevices = await this.codeReader.listVideoInputDevices();
            
            if (videoInputDevices.length === 0) {
                throw new Error('Nenhuma câmera encontrada');
            }
            
            return videoInputDevices;
        } catch (error) {
            console.error('Erro ao inicializar scanner:', error);
            throw error;
        }
    }

    async startScanner(deviceId = null) {
        if (!this.codeReader) return;

        try {
            await this.codeReader.decodeFromVideoDevice(
                deviceId,
                'scanner-viewport',
                (result, error) => {
                    if (result) {
                        this.onScanSuccess(result.text);
                    }
                    if (error && !error.message.includes('NotFoundException')) {
                        console.log('Scanning:', error.message);
                    }
                }
            );
            
            this.isScanning = true;
        } catch (error) {
            console.error('Erro ao iniciar scanner:', error);
            throw error;
        }
    }

    stopScanner() {
        if (this.codeReader && this.isScanning) {
            this.codeReader.reset();
            this.isScanning = false;
        }
    }

    onScanSuccess(decodedText) {
        const cleanCode = this.cleanCode(decodedText);
        document.getElementById('numero_bem').value = cleanCode;
        
        // Feedback visual
        this.showScanFeedback();
        
        // Fechar modal e focar no próximo campo
        const scannerModal = bootstrap.Modal.getInstance(document.getElementById('scannerModal'));
        if (scannerModal) {
            scannerModal.hide();
        }
        
        setTimeout(() => {
            document.getElementById('localizacao').focus();
        }, 300);
    }

    cleanCode(code) {
        return code.trim()
            .replace(/[^\w\-\s]/g, '')
            .replace(/\s+/g, ' ')
            .toUpperCase();
    }

    showScanFeedback() {
        // Implementar feedback visual/sonoro
        if (navigator.vibrate) {
            navigator.vibrate([100, 50, 100]);
        }
    }
}