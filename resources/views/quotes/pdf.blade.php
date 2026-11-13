<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Orçamento {{ $quote->display_number }}</title>
    <style>
        @page { margin: 36px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.5; }
        h1 { margin: 0; font-size: 20px; color: #111827; }
        h2 { margin: 22px 0 8px; font-size: 12px; color: #111827; }
        .muted { color: #6b7280; }
        .header { border-bottom: 2px solid #465fff; padding-bottom: 16px; }
        .meta { margin-top: 14px; }
        .meta td { padding: 3px 18px 3px 0; vertical-align: top; }
        table.items { border-collapse: collapse; width: 100%; margin-top: 10px; }
        .items th { background: #f3f4f6; color: #374151; text-align: left; }
        .items th, .items td { border-bottom: 1px solid #e5e7eb; padding: 9px 8px; vertical-align: top; }
        .right { text-align: right !important; }
        .total { margin-top: 18px; text-align: right; font-size: 15px; font-weight: bold; color: #111827; }
        .note { white-space: pre-line; }
        .footer { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Orçamento nº {{ $quote->display_number }}</h1>
        <div class="muted">{{ $quote->tenant?->name }} · {{ $quote->title }}</div>
    </div>
    <table class="meta">
        <tr><td><strong>Cliente</strong><br>{{ $quote->client?->name }}<br><strong>Telefone:</strong> {{ $quote->client?->phone ?: 'Não informado' }}<br><strong>E-mail:</strong> {{ $quote->client?->email ?: 'Não informado' }}</td><td><strong>Emissão</strong><br>{{ $quote->created_at?->copy()->timezone('America/Sao_Paulo')->format('d/m/Y') }}</td><td><strong>Validade</strong><br>{{ $quote->valid_until?->format('d/m/Y') ?? 'Não informada' }}</td></tr>
    </table>
    @if($quote->description)
        <h2>Descrição</h2><div class="note">{{ $quote->description }}</div>
    @endif
    <h2>Itens</h2>
    <table class="items">
        <thead><tr><th>Descrição</th><th>Quantidade</th><th class="right">Preço unitário</th><th class="right">Desconto</th><th class="right">Total</th></tr></thead>
        <tbody>
        @foreach($quote->items as $item)
            <tr>
                <td>{{ $item->description_snapshot }}</td>
                <td>{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->unit }}</td>
                <td class="right">R$ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}</td>
                <td class="right">R$ {{ number_format($item->discount_cents / 100, 2, ',', '.') }}</td>
                <td class="right">R$ {{ number_format($item->subtotal_cents / 100, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if($quote->discount_cents)
        <div class="right">Desconto geral: − R$ {{ number_format($quote->discount_cents / 100, 2, ',', '.') }}</div>
    @endif
    @if($quote->extra_cents)
        <div class="right">Acréscimo: R$ {{ number_format($quote->extra_cents / 100, 2, ',', '.') }}</div>
    @endif
    <div class="total">Valor total: R$ {{ number_format($quote->total_cents / 100, 2, ',', '.') }}</div>
    @if($quote->payment_terms)
        <h2>Condições de pagamento</h2><div class="note">{{ $quote->payment_terms }}</div>
    @endif
    @if($quote->public_notes)
        <h2>Observações</h2><div class="note">{{ $quote->public_notes }}</div>
    @endif
    <div class="footer">Documento gerado em {{ now('America/Sao_Paulo')->format('d/m/Y H:i') }} (horário de São Paulo).</div>
</body>
</html>
