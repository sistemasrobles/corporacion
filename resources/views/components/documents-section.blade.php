@props(['documents'])

<section class="docs-card" aria-label="Documentos anexos">
    {{-- Header --}}
    <div class="docs-head">
        <h3>Documentos anexos</h3>
        <span class="docs-count">{{ $completedCount = count(array_filter($documents, fn($d) => !empty($d['value']))) }} de {{ count($documents) }}</span>
    </div>

    {{-- Regla global --}}
    <div class="docs-rule">
        <span>Formatos aceptados:</span>
        <span class="tagpill">PDF</span>
        <span class="tagpill">JPG</span>
        <span class="tagpill">PNG</span>
        <span>· máx. 10&nbsp;MB c/u</span>
        @if(in_array(true, array_column($documents, 'required')))
            <span class="tagpill tagpill--req">✓ obligatorio</span>
        @endif
    </div>

    {{-- Filas de documentos --}}
    <div class="docs-rows">
        @foreach($documents as $doc)
            <div class="doc-row" data-doc="{{ $doc['key'] }}">
                {{-- Icono --}}
                <div class="doc-ico"><span class="fold"></span></div>

                {{-- Nombre + etiqueta --}}
                <div class="doc-meta">
                    <div class="doc-name">{{ $doc['name'] }}</div>
                    <div class="doc-tag {{ $doc['required'] ? 'doc-tag--req' : 'doc-tag--opt' }}">
                        {{ $doc['required'] ? 'obligatorio' : 'opcional' }}
                    </div>
                </div>

                {{-- Acción: dropzone o archivo cargado --}}
                <div class="doc-row__action">
                    <span class="doc-dropzone" data-key="{{ $doc['key'] }}">
                        Arrastra o <b>Examinar</b>
                    </span>
                </div>
            </div>
        @endforeach
    </div>
</section>

<style>
    .docs-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
    }

    .docs-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }

    .docs-head h3 {
        font-weight: 600;
        font-size: 1.125rem;
        margin: 0;
        color: #1e293b;
    }

    .docs-count {
        font-size: 0.875rem;
        border: 1px solid #cbd5e1;
        border-radius: 20px;
        padding: 2px 10px;
        color: #64748b;
        background: #f1f5f9;
    }

    .docs-rule {
        font-size: 0.9375rem;
        color: #64748b;
        border-bottom: 1px dashed #cbd5e1;
        padding-bottom: 12px;
        margin-bottom: 14px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .tagpill {
        font-size: 0.8125rem;
        border: 1px solid #cbd5e1;
        border-radius: 20px;
        padding: 2px 10px;
        color: #64748b;
        background: #f1f5f9;
    }

    .tagpill--req {
        border-color: #3b82f6;
        color: #3b82f6;
        background: #eff6ff;
    }

    .docs-rows {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .doc-row {
        display: grid;
        grid-template-columns: 34px 1fr auto;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .doc-row:hover {
        border-color: #cbd5e1;
        background: #ffffff;
    }

    .doc-ico {
        width: 30px;
        height: 36px;
        border: 2px solid #1e293b;
        border-radius: 3px;
        position: relative;
        background: #ffffff;
    }

    .doc-ico::after {
        content: "";
        position: absolute;
        top: 4px;
        left: 5px;
        right: 5px;
        height: 1px;
        background: #cbd5e1;
        box-shadow: 0 5px 0 #cbd5e1, 0 10px 0 #cbd5e1;
    }

    .doc-ico .fold {
        position: absolute;
        top: -2px;
        right: -2px;
        width: 0;
        height: 0;
        border-left: 9px solid transparent;
        border-top: 9px solid #1e293b;
    }

    .doc-meta {
        min-width: 0;
    }

    .doc-name {
        font-size: 1rem;
        font-weight: 500;
        color: #1e293b;
        line-height: 1.1;
    }

    .doc-tag {
        font-size: 0.8125rem;
        font-weight: 500;
        margin-top: 2px;
    }

    .doc-tag--req {
        color: #3b82f6;
    }

    .doc-tag--opt {
        color: #f97316;
    }

    .doc-row__action {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .doc-dropzone {
        font-size: 0.9375rem;
        color: #64748b;
        border: 1px dashed #cbd5e1;
        border-radius: 6px;
        padding: 6px 12px;
        white-space: nowrap;
        cursor: pointer;
        background: repeating-linear-gradient(
            45deg,
            #ffffff,
            #ffffff 6px,
            #f8fafc 6px,
            #f8fafc 12px
        );
        transition: all 0.2s ease;
    }

    .doc-dropzone:hover {
        border-color: #94a3b8;
        background: #f8fafc;
    }

    .doc-dropzone b {
        color: #3b82f6;
        font-weight: 600;
    }

    .doc-file {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #10b981;
        background: #dcfce7;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 0.9375rem;
        color: #166534;
        white-space: nowrap;
    }

    .doc-file__check {
        color: #10b981;
        font-weight: bold;
    }

    .doc-file__remove {
        width: 18px;
        height: 18px;
        border: 1.5px solid #166534;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 12px;
        cursor: pointer;
        color: #166534;
        background: transparent;
        transition: all 0.2s ease;
    }

    .doc-file__remove:hover {
        background: #166534;
        color: #dcfce7;
    }
</style>
