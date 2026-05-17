<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificate_title }}</title>
    <style>
        @page {
            margin: 0;
            size: A4 landscape;
        }

        body {
            margin: 0;
            padding: {{ !empty($is_print_preview) ? '0' : '6mm' }};
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            background: {{ !empty($is_print_preview) ? '#1f2937' : '#f8fafc' }};
        }

        .preview-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
        }

        .certificate {
            position: relative;
            overflow: hidden;
            border: 1mm solid #14532d;
            background: #ffffff;
            padding: 10mm 12mm;
            text-align: center;
            width: 100%;
            max-width: 297mm;
            min-height: 210mm;
            box-sizing: border-box;
            box-shadow: {{ !empty($is_print_preview) ? '0 20px 60px rgba(15, 23, 42, 0.35)' : 'none' }};
        }

        .background-image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            font-size: 10pt;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            color: #64748b;
        }

        .title {
            margin: 6mm 0 0;
            font-size: 22pt;
            font-weight: 800;
            color: #14532d;
        }

        .subtitle {
            margin: 3mm 0 0;
            font-size: 11pt;
            color: #334155;
        }

        .student-name {
            margin: 6mm 0 0;
            font-size: 24pt;
            font-weight: 700;
            color: #0f172a;
        }

        .divider {
            width: 120mm;
            margin: 2mm auto 0;
            border-top: 0.6mm solid #cbd5e1;
        }

        .completion-text {
            margin: 5mm 0 0;
            font-size: 11pt;
            color: #334155;
        }

        .course-title {
            margin: 4mm 0 0;
            font-size: 17pt;
            font-weight: 700;
            color: #14532d;
        }

        .meta {
            margin-top: 10mm;
            font-size: 9.5pt;
            color: #334155;
        }

        .meta strong {
            color: #0f172a;
        }

        .meta-row {
            margin-top: 2mm;
        }

        .signature {
            margin-top: 8mm;
            text-align: right;
        }

        .signature img {
            max-width: 42mm;
            max-height: 16mm;
        }

        .signature-name {
            margin-top: 2mm;
            font-size: 10pt;
            font-weight: 700;
            color: #0f172a;
        }

        .signature-title {
            margin-top: 1mm;
            font-size: 9pt;
            color: #64748b;
        }

        .footer-note {
            margin-top: 6mm;
            font-size: 8.5pt;
            color: #94a3b8;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .preview-shell {
                min-height: auto;
                padding: 0;
            }

            .certificate {
                max-width: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="preview-shell">
        <div class="certificate">
            @if ($background_image)
                <img class="background-image" src="{{ $background_image }}" alt="">
            @endif

            <div class="content">
                <div class="eyebrow">{{ $organization_name }}</div>
                <h1 class="title">{{ $certificate_title }}</h1>
                <p class="subtitle">This certificate is presented to</p>

                <div class="student-name">{{ $student_name }}</div>
                <div class="divider"></div>

                <p class="completion-text">for successfully completing the class</p>
                <div class="course-title">{{ $course_title }}</div>

                <div class="meta">
                    <div class="meta-row"><strong>Certificate Number:</strong> {{ $certificate_number }}</div>
                    <div class="meta-row"><strong>Issue Date:</strong> {{ $issue_date }}</div>
                    <div class="meta-row"><strong>Issued At:</strong> {{ $issued_at }}</div>
                    @if ($expired_at)
                        <div class="meta-row"><strong>Expired At:</strong> {{ $expired_at }}</div>
                    @endif
                </div>

                @if ($signatory_name || $signatory_title || $signature_image)
                    <div class="signature">
                        @if ($signature_image)
                            <img src="{{ $signature_image }}" alt="">
                        @endif
                        @if ($signatory_name)
                            <div class="signature-name">{{ $signatory_name }}</div>
                        @endif
                        @if ($signatory_title)
                            <div class="signature-title">{{ $signatory_title }}</div>
                        @endif
                    </div>
                @endif

                @if ($footer_note)
                    <div class="footer-note">{{ $footer_note }}</div>
                @endif
            </div>
        </div>
    </div>

    @if (!empty($is_print_preview) && !empty($trigger_print))
        <script>
            window.addEventListener("load", function () {
                window.setTimeout(function () {
                    window.print();
                }, 250);
            });

            window.addEventListener("afterprint", function () {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage(
                        { type: "student-certificate-print-finished" },
                        window.location.origin
                    );
                }
            });
        </script>
    @endif
</body>
</html>
