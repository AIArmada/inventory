<div class="p-4 space-y-4 max-h-96 overflow-y-auto">
    @foreach($snapshot as $key => $value)
        <div>
            <span class="font-medium text-gray-700">{{ Str::headline($key) }}:</span>
            @if(is_array($value))
                <pre
                    class="mt-1 p-2 bg-gray-100 rounded text-sm overflow-x-auto">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
            @else
                <span class="text-gray-600">{{ $value ?? '-' }}</span>
            @endif
        </div>
    @endforeach
</div>