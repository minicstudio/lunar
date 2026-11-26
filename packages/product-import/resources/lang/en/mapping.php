<?php

return [
    'errors' => [
        'product_type_required' => 'Choose a product type.',
        'unknown_target' => 'Column ":column" maps to a field that does not exist.',
        'locale_required' => 'Choose a language for column ":column".',
        'unknown_locale' => 'Column ":column" uses a language that the store does not have.',
        'duplicate_target' => ':target is mapped twice.',
        'duplicate_target_locale' => ':target (:locale) is mapped twice.',
        'duplicate_option' => 'The option ":option" is mapped twice.',
        'option_required' => 'Choose an existing option or name a new one for column ":column".',
        'unknown_option' => 'The option of column ":column" no longer exists.',
        'group_column_required' => 'Grouping by column needs a column mapped to Group key.',
        'weight_unit_required' => 'Choose the weight unit of the sheet ":sheet".',
        'number_format_required' => 'Choose the number format of the column ":column" on the sheet ":sheet".',
        'date_format_required' => 'Choose the date format of the column ":column" on the sheet ":sheet".',
        'item_parts_invalid' => 'The item parts of the column ":column" may name one name and one value.',
        'image_suffix_invalid' => 'The image size suffix of the column ":column" may hold only letters, digits, "-", and "_", and at most 50 characters.',
        'pair_required' => 'Choose the pair number of column ":column".',
        'duplicate_target_pair' => ':target of pair :pair is mapped twice.',
        'pair_without_value' => 'Pair :pair has an option name column but no option value column.',
        'parent_column_required' => 'Grouping by parent needs a column mapped to Parent reference.',
        'length_unit_required' => 'Choose the length unit of the sheet ":sheet".',
        'list_separator_required' => 'Choose the list separator of the column ":column" on the sheet ":sheet".',
        'path_separator_required' => 'Choose the category level separator of the column ":column" on the sheet ":sheet".',
        'item_separator_required' => 'Choose the item part separator of the column ":column" on the sheet ":sheet".',
    ],
    'formats' => [
        'number' => ':decimal and :thousands',
        'missing' => 'no number format',
        'decimal' => [
            'dot' => 'a decimal dot',
            'comma' => 'a decimal comma',
        ],
        'thousands' => [
            'none' => 'no thousands separator',
            'dot' => 'a dot thousands separator',
            'comma' => 'a comma thousands separator',
            'space' => 'a space thousands separator',
            'apostrophe' => 'an apostrophe thousands separator',
        ],
    ],
];
