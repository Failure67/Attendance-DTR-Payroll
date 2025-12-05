<div class="table-container {{ $tableClass }}">

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
            @forelse ($tableData as $row)
                <tr>
                    @foreach ($row as $index => $data)
                        <td class="table-data {{ $tableClass }} {{ $tableCol[$index] }}">
                            {!! $data !!}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($tableLabel) }}" style="text-align: center; padding: 2rem;">
                        No results found
                    </td>
                </tr>
            @endforelse
        </tbody>

    </table>

</div>