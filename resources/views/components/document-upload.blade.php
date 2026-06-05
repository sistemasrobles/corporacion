@props(['documents'])

<div style="display: flex; flex-direction: column; gap: 1rem;">
    {{-- Header --}}
    <div style="color: #64748b; font-size: 0.875rem;">
        <strong>Formatos aceptados:</strong> PDF · JPG · PNG · máx. 10 MB c/u
    </div>

    {{-- Documents List --}}
    @foreach($documents as $doc)
        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1rem; align-items: stretch;">
            {{-- Left: Document Info Box --}}
            <div style="
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 1.25rem;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                background: #ffffff;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            ">
                <span style="font-size: 1.75rem; flex-shrink: 0;">📄</span>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem;">
                        {{ $doc['name'] }}
                    </div>
                    <div style="
                        color: {{ $doc['required'] ? '#3b82f6' : '#f97316' }};
                        font-size: 0.75rem;
                        font-weight: 600;
                        margin-top: 0.25rem;
                    ">
                        {{ $doc['required'] ? 'obligatorio' : 'opcional' }}
                    </div>
                </div>
            </div>

            {{-- Right: File Upload Area --}}
            <div style="
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.25rem;
                border: 1px solid #374151;
                border-radius: 0.75rem;
                background: #1f2937;
                color: #9ca3af;
                font-size: 0.95rem;
                text-align: center;
                min-height: 80px;
                cursor: pointer;
                transition: all 0.2s;
            ">
                Drag & Drop your files or <strong style="color: #ffffff; margin-left: 0.25rem;">Browse</strong>
            </div>
        </div>
    @endforeach
</div>
