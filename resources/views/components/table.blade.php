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
            @foreach ($tableData as $row)
                <tr>
                    @foreach ($row as $index => $data)
                        <td class="table-data {{ $tableClass }} {{ $tableCol[$index] }}">
                            {{ $data }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>

    </table>

</div>