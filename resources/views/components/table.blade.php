<div class="table-container {{ $tableClass }}">
    @php
        // Columns that should render raw HTML instead of escaped text
        $rawColumns = $rawColumns ?? [];
        // Optional sortable columns: [columnKey => sortKey]
        $sortableColumns = $sortableColumns ?? [];
        $currentSortBy = $currentSortBy ?? null;
        $currentSortDir = $currentSortDir ?? 'asc';
    @endphp

    <table>
        <thead>
            <tr>
                @foreach ($tableLabel as $index => $label)
                    @php
                        $columnKey = $tableCol[$index] ?? ('col-' . $index);
                        $isSortable = array_key_exists($columnKey, $sortableColumns);
                        $sortKey = $isSortable ? $sortableColumns[$columnKey] : null;
                        $isActiveSort = $isSortable && $currentSortBy === $sortKey;
                        $sortDirForHeader = $isActiveSort ? $currentSortDir : 'asc';
                    @endphp
                    <th class="table-col {{ $tableClass }} {{ $columnKey }}" data-label="{{ $label }}"
                        @if ($isSortable)
                            data-sort-key="{{ $sortKey }}"
                            data-sort-active="{{ $isActiveSort ? '1' : '0' }}"
                            data-sort-dir="{{ $sortDirForHeader }}"
                        @endif
                    >
                        {{ $label }}
                        @if ($isSortable)
                            <span class="sort-indicator">
                                @if ($isActiveSort)
                                    {!! $sortDirForHeader === 'asc' ? '&uarr;' : '&darr;' !!}
                                @else
                                    <span class="text-muted" style="color: #f1f1f1 !important;">&udarr;</span>
                                @endif
                            </span>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($tableData as $row)
                <tr>
                    @foreach ($tableCol as $index => $columnKey)
                        @php
                            $cell = $row[$index] ?? null;
                            if ($cell === null && is_array($row) && array_key_exists($columnKey, $row)) {
                                $cell = $row[$columnKey];
                            }
                            $cell = $cell ?? '';
                        @endphp
                        <td class="table-data {{ $tableClass }} {{ $columnKey }}">
                            @if (in_array($columnKey, $rawColumns))
                                {!! $cell !!}
                            @else
                                {{ $cell }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>