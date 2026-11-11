<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factură comandă</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * {
            font-size:12px;
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        .lines {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .discount-seperator {
            color:#ccc;
        }
        .lines-heading {
            text-align: left;
            background-color: #ededed;
        }

        .lines-heading th {
            padding: 5px 10px;
            border: 1px solid #ccc;
        }

        .lines-body td {
            padding: 5px 10px;
            border: 1px solid #ededed;
        }

        .lines-heading small,
        .lines-body small {
            font-size: 10px;
            font-weight: normal;
            color: #777;
        }

        .lines .amount {
            text-align: right;
        }

        .lines-footer {
            border-top:5px solid #f5f5f5;
            text-align:right;
        }

        .lines-footer td {
            padding: 10px;
            border: 1px solid #ededed;
        }

        .summary {
            margin-bottom: 40px;
        }

        .summary td {
            padding: 5px 10px;
        }

        .info {
            color:#0099e5;
        }

        .summary .total td {
            border-top: 1px solid #ccc;
        }


    </style>
</head>

<body>
    <div class="content">
        <div class="invoice-box">

            <table cellpadding="0" cellspacing="0" width="100%">
                <tr class="top">
                    <td>
                        <table width="100%">
                            <tr>
                                <td class="title" width="50%">
                                    <h1>{{ config('app.name') }}</h1>
                                </td>
                                <td align="right" width="50%">
                                    Factură: {{ @$record->reference }} <br>
                                    Creată: {{ $record->placed_at }}<br>
                                    @if($invoice->showsVat())
                                      Cota TVA: {{ $invoice->vatPercentage() }}%<br>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table width="100%">
                            <tr>
                                <td align="left" width="33%">
                                    <h3>Adresa de facturare</h3>
                                    {{ $record->billingAddress->fullName }}<br>
                                    @if($record->billingAddress->company_name)
                                      {{ $record->billingAddress->company_name }}<br>
                                    @endif
                                    @if($record->billingAddress->tax_identifier)
                                      {{ $record->billingAddress->tax_identifier }}<br>
                                    @endif
                                    {{ $record->billingAddress->line_one }}
                                    @if($record->billingAddress->line_two)
                                      <br>{{ $record->billingAddress->line_two }}<br>
                                    @endif
                                    @if($record->billingAddress->line_three)
                                      <br>{{ $record->billingAddress->line_three }}<br>
                                    @endif
                                    {{ $record->billingAddress->city }}<br>
                                    {{ $record->billingAddress->state }}<br>
                                    {{ $record->billingAddress->postcode }}<br>
                                    {{ $record->billingAddress->country->name }}<br>
                                    @if($record->customer?->tax_identifier)
                                        <p>Cod fiscal: {{ $record->customer?->tax_identifier }}</p>
                                    @endif
                                </td>

                                @if ($record->shippingAddress)
                                <td align="left" width="33%">
                                    <h3>Adresa de livrare</h3>
                                    {{ $record->shippingAddress->fullName }}<br>
                                    @if($record->shippingAddress->company_name)
                                      {{ $record->shippingAddress->company_name }}<br>
                                    @endif
                                    @if($record->shippingAddress->tax_identifier)
                                      {{ $record->shippingAddress->tax_identifier }}<br>
                                    @endif
                                    {{ $record->shippingAddress->line_one }}
                                    @if($record->shippingAddress->line_two)
                                      <br>{{ $record->shippingAddress->line_two }}<br>
                                    @endif
                                    @if($record->shippingAddress->line_three)
                                      <br>{{ $record->shippingAddress->line_three }}<br>
                                    @endif
                                    {{ $record->shippingAddress->city }}<br>
                                    {{ $record->shippingAddress->state }}<br>
                                    {{ $record->shippingAddress->postcode }}<br>
                                    {{ $record->shippingAddress->country->name }}<br>
                                </td>
                                @endif

                                <td align="right" width="33%">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table cellpadding="0" cellspacing="0" width="100%" class="lines">
                <thead class="lines-heading">
                    <tr width="100%">
                        <th width="5%">
                            #
                        </th>
                        <th width="{{ $invoice->showsVat() ? 40 : 55 }}%">
                            Denumire
                        </th>
                        <th width="8%">
                            U.M.
                        </th>
                        <th width="7%">
                            Cant.
                        </th>
                        <th width="13%" class="amount">
                            {{ $invoice->showsVat() ? 'Preț fără TVA' : 'Preț unitar' }}
                            <br><small>{{ $record->currency_code }}</small>
                        </th>
                        <th width="12%" class="amount">
                            Valoare
                            <br><small>{{ $record->currency_code }}</small>
                        </th>
                        @if($invoice->showsVat())
                          <th width="15%" class="amount">
                              Valoare TVA
                              <br><small>{{ $record->currency_code }}</small>
                          </th>
                        @endif
                    </tr>
                </thead>
                <tbody class="lines-body">
                  @foreach($invoice->rows() as $row)
                    <tr>
                      <td>
                        {{ $loop->iteration }}
                      </td>
                      <td>
                        {{ $row['name'] }}
                        @if($row['details'])
                          <br><small>{{ $row['details'] }}</small>
                        @endif
                      </td>
                      <td>
                        {{ $row['unit'] }}
                      </td>
                      <td>
                        {{ $row['quantity'] }}
                      </td>
                      <td class="amount">
                        {{ $invoice->amount($row['unitPrice']) }}
                      </td>
                      <td class="amount">
                        {{ $invoice->amount($row['value']) }}
                      </td>
                      @if($invoice->showsVat())
                        <td class="amount">
                          {{ $invoice->amount($row['vat']) }}
                        </td>
                      @endif
                    </tr>
                  @endforeach
                </tbody>
                <tfoot class="lines-footer">
                    @if($invoice->showsVat())
                      <tr>
                          <td colspan="5"><strong>Total</strong></td>
                          <td>{{ $invoice->amount($invoice->netTotal()) }}</td>
                          <td>{{ $invoice->amount($invoice->vatTotal()) }}</td>
                      </tr>
                    @endif
                    <tr>
                        <td colspan="5"><strong>Total factură</strong></td>
                        <td colspan="{{ $invoice->showsVat() ? 2 : 1 }}"><strong>{{ $invoice->amount($invoice->total()) }}</strong></td>
                    </tr>
                </tfoot>
            </table>

            @if($record->notes)
            <p><strong>Note comandă</strong><br>
            {{ $record->notes }}</p>
            <br>
            @endif
        </div>
    </div>
</body>
</html>
