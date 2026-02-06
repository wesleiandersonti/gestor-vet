<div class="d-flex flex-wrap gap-2">
    <!-- Botão Deletar -->
    <form action="{{ $deleteRoute }}" method="POST" style="display:inline;">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Deletar">
            <i class="fas fa-trash-alt"></i>
        </button>
    </form>

    <!-- Botão Editar -->
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
        data-bs-target="#editClient{{ $cliente->id }}" 
        data-bs-toggle="tooltip" data-bs-placement="top" title="Editar">
        <i class="fas fa-edit"></i>
    </button>

    <!-- Botão Cobrança Manual -->
    <form action="{{ $chargeRoute }}" method="POST" style="display:inline;">
        @csrf
        @method('POST')
        <button type="submit" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" data-bs-placement="top" title="Cobrança Manual">
            <i class="fas fa-dollar-sign"></i>
        </button>
    </form>

    <!-- Botão Detalhes da Cobrança -->
    <a href="{{ $detailsRoute }}" class="btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Detalhes da Cobrança">
        <i class="fas fa-thumbs-up"></i>
    </a>

    <!-- Botão Enviar Dados de Login -->
    <button class="btn btn-sm btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Enviar Dados de Login" onclick="sendLoginDetails('{{ $cliente->id }}')">
        <i class="fab fa-whatsapp"></i>
    </button>
</div>