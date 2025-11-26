<div class="table-container {{ $tableClass }}">
    @php
        // Columns that should render raw HTML instead of escaped text
        $rawColumns = $rawColumns ?? [];
    @endphp

    <table>
        <thead>
            <tr>
                @foreach ($tableLabel as $index => $label)
                    <th class="table-col {{ $tableClass }} {{ $tableCol[$index] }}" data-label="{{ $label }}">
                        {{ $label }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($tableData as $row)
                <tr>
                    @foreach ($row as $index => $data)
                        <td class="table-data {{ $tableClass }} {{ $tableCol[$index] }}">
                            @if (in_array($tableCol[$index], $rawColumns))
                                {!! $data !!}
                            @else
                                {{ $data }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>