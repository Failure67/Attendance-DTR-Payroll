<div class="table-container {{ $tableClass }}">

    <table>

        <thead>
            <tr>
                @foreach ($tableCol as $index => $label)
                    <td class="table-col {{ $key }}" data-label="{{ $label }}">
                        
                    </td>
                @endforeach
            </tr>
        </thead>

    </table>

</div>