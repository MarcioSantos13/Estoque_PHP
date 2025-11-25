<?php
// usar_arquivo.php - Usar arquivo da pasta uploads para importação
if (!isset($_GET['arquivo'])) {
    die('❌ Nenhum arquivo especificado');
}

$nome_arquivo = $_GET['arquivo'];
$caminho_arquivo = __DIR__ . '\\uploads\\' . $nome_arquivo;

// Verificar se arquivo existe
if (!file_exists($caminho_arquivo)) {
    die('❌ Arquivo não encontrado: ' . $caminho_arquivo);
}

$tamanho = filesize($caminho_arquivo);
$tamanho_mb = round($tamanho / 1024 / 1024, 2);

echo "<h3>🚀 Preparando Importação</h3>";
echo "<div class='alert alert-success'>";
echo "<h5><i class='bi bi-check-circle me-2'></i>Arquivo Selecionado</h5>";
echo "<strong>📄 Nome:</strong> $nome_arquivo<br>";
echo "<strong>📏 Tamanho:</strong> $tamanho_mb MB<br>";
echo "<strong>📍 Local:</strong> $caminho_arquivo<br>";
echo "</div>";

// Formulário para configurar a importação
?>
<form action="api/importar.php" method="POST" id="formImportarUploads">
    <input type="hidden" name="arquivo_uploads" value="<?php echo htmlspecialchars($nome_arquivo); ?>">
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="bi bi-gear me-2"></i>Configuração da Importação
            </h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="nome_aba" class="form-label">Nome da Aba</label>
                <input type="text" class="form-control" id="nome_aba" name="nome_aba" value="Plan1" required>
                <div class="form-text">Nome da planilha no Excel (geralmente "Plan1" ou "Sheet1")</div>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-play-circle me-2"></i>Iniciar Importação
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Área de Resultados -->
<div id="resultado" class="mt-4" style="display: none;">
    <div class="alert alert-info">
        <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm me-3" role="status"></div>
            <div>
                <h6 class="mb-1">Processando importação...</h6>
                <p class="mb-0 small">Aguarde enquanto os dados são importados.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formImportarUploads').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    const resultado = document.getElementById('resultado');
    
    // Mostrar loading
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spinner-border spinner-border-sm me-2"></i>Importando...';
    resultado.style.display = 'block';
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('api/importar_uploads.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultado.innerHTML = `
                <div class="alert alert-success">
                    <h5><i class="bi bi-check-circle me-2"></i>Importação Concluída!</h5>
                    <p>${data.message}</p>
                    <pre class="mt-2">${JSON.stringify(data.detalhes, null, 2)}</pre>
                    <div class="mt-3">
                        <a href="sistema_crud.php" class="btn btn-success">Ver Bens</a>
                        <a href="importar.php" class="btn btn-outline-primary">Importar Outro</a>
                    </div>
                </div>
            `;
        } else {
            resultado.innerHTML = `
                <div class="alert alert-danger">
                    <h5><i class="bi bi-exclamation-triangle me-2"></i>Erro</h5>
                    <p>${data.message}</p>
                </div>
            `;
        }
    } catch (error) {
        resultado.innerHTML = `
            <div class="alert alert-danger">
                <h5><i class="bi bi-exclamation-triangle me-2"></i>Erro na Requisição</h5>
                <p>${error.message}</p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle me-2"></i>Iniciar Importação';
    }
});
</script>