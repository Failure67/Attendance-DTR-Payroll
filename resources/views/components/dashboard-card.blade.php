<div class="dashboard-card {{ $cardClass }}">

    <div class="dashboard-card-container">

        <span class="dashboard-card-title">
            {{ $label }}
        </span>

    </div>

    <div class="dashboard-card-table">

        <table>

            <thead>
                <tr>
                    @foreach ($tableCol as $index => $col)
                        <th class="{{ $cardClass }} {{ $tableClass[$index] }}">
                            {{ $col }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($tableData as $row)
                <tr>
                    @foreach ($row as $index => $data)
                        <td class="{{ $cardClass }} {{ $tableClass[$index] }}">
                            {{ $data }}
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>

        </table>

    </div>

    <hr>

    <div class="dashboard-card-view-all">

        <a href="{{ $viewAll }}">

            <span class="dashboard-card-view-all-btn">

                <span class="icon">
                    <i class="fa-solid fa-arrow-right"></i>
                </span>
                
                <span class="label">
                    View all..
                </span>

            </span>

        </a>

    </div>

</div>