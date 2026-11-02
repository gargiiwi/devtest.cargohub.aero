<?php

// Path to the PDF file
$pdfFilePath = '1234.pdf';

// Path to store the generated HTML file
$htmlFilePath = 'output234.html';

//$output = shell_exec('ls -lart');
//echo "<pre>$output</pre>";


// Execute pdftohtml command
// shell_exec("/usr/bin/pdftohtml -c $pdfFilePath $htmlFilePath");


shell_exec("/usr/bin/pdftohtml -c $pdfFilePath $htmlFilePath", $output, $return_var);

if ($return_var === 0) {
    echo "Conversion successful.";
} else {
    echo "Conversion failed with error code: $return_var";
    echo "Output: " . implode("\n", $output);
}


// Check if HTML file was generated successfully
if (file_exists($htmlFilePath)) {
    echo "PDF successfully converted to HTML.";
} else {
    echo "Failed to convert PDF to HTML.";
}

?>