<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $doc->doc_type }} - {{ $doc->doc_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .content {
            white-space: pre-line;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
        }
        .doc-summary {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }
        .doc-summary dt {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .doc-summary dd {
            font-weight: 600;
            margin-bottom: 12px;
            margin-left: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        @if($company = config('docs.company.name'))
            <strong>{{ $company }}</strong>
        @endif
    </div>

    <div class="content">
        {!! nl2br(e($body)) !!}
    </div>

    <div class="doc-summary">
        <dl>
            <dt>Document Number</dt>
            <dd>{{ $doc->doc_number }}</dd>

            <dt>Issue Date</dt>
            <dd>{{ $doc->issue_date->format('F j, Y') }}</dd>

            @if($doc->due_date)
                <dt>Due Date</dt>
                <dd>{{ $doc->due_date->format('F j, Y') }}</dd>
            @endif

            <dt>Total</dt>
            <dd>{{ $doc->currency }} {{ number_format((float) $doc->total, 2) }}</dd>
        </dl>
    </div>

    <div class="footer">
        <p>This email was sent regarding {{ ucfirst(str_replace('_', ' ', $doc->doc_type)) }} #{{ $doc->doc_number }}.</p>
        @if(config('docs.company.email'))
            <p>Questions? Contact us at {{ config('docs.company.email') }}</p>
        @endif
    </div>

    @if($trackingPixelUrl)
        <img src="{{ $trackingPixelUrl }}" width="1" height="1" alt="" style="display:none;">
    @endif
</body>
</html>
