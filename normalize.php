<?php
$file = 'resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
$c = file_get_contents($file);
file_put_contents($file, str_replace("\r\n", "\n", $c));
echo "Done\n";

