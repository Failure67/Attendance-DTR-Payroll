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
                        $columnKey = $tableCol[$index];
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
                                    <span class="text-muted">&udarr;</span>
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