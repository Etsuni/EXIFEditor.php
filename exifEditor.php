<?php

    /**
     * Classe qui permet d'éditer facilement les tags Exif d'une image
     *
     * Basé sur https://blog.jacobemerick.com/web-development/manipulating-jpeg-exif-headers/
     * Dépendance : Imagick
     *
     * Les informations pour trouver les champs correspondant se trouvent à ce lien
     *  https://www.media.mit.edu/pia/Research/deepview/exif.html#IFD0Tags
     * Il faut transformer la valeur hexadécimale en hexadécimale
     *
     * @author     Nicolas Guilloux
     * http://nicolasguilloux.eu/
     */
    class exifEditor {

        const DEV = false;

        private static $EXIF_IDS = array(
            array(
                'name'    => 'Title',
                'id'      => 'exif:WinXP-Title',
                'type'    => 40091,
                'windows' => true
            ),
            array(
                'name'   => 'Object',
                'id'     => 'exif:ImageDescription',
                'type'   => 270
            ),
            array(
                'name'   => 'DocumentName',
                'id'     => 'exif:DocumentName',
                'type'   => 269
            ),
            array(
                'name'   => 'Author',
                'id'     => 'exif:Author',
                'type'   => 315
            ),
            array(
                'name'   => 'Date',
                'id'     => 'exif:Date',
                'type'   => 306
            ),
            array(
                'name'   => 'Comment',
                'id'     => 'exif:UserComment',
                'type'   => 37510
            ),
            array(
                'name'   => 'Copyright',
                'id'     => 'exif:Copyright',
                'type'   => 33432
            )
        );

        /** Constructor
         *
         * @param string $filename Chemin du fichier ciblé
         */
        function __construct($filename) {
            $this->filename = $filename;
            $this->exifData = array();

            // Récupération des données déjà existantes
            # Avec Imagick
    		if (extension_loaded('imagick')) {
                $img = new imagick($filename);
                $exifArray = $img->getImageProperties("exif:*");

                if(DEV) var_dump($exifArray);

                foreach ($exifArray as $id => $value) {
                    $i = self::find(self::$EXIF_IDS, 'id', $id);

                    if( $i >= 0 ) {
                        if( isset(self::$EXIF_IDS[$i]['windows']) && self::$EXIF_IDS[$i]['windows'] )
                            $value = self::windowsFormat($value, true);

                        self::update($id, $value);
                    }
                }
            }

            /* Solution sans Imagick
                $exif = exif_read_data('goo.jpg', 0, true);

                foreach ($exif as $key => $section) {
                    foreach ($section as $name => $val) {
                        echo "$key.$name: $val<br />\n";
                    }
                }
            */
        }

        /** Met à jour un des champs
         *
         * @param string $key  Identifiant du champ à modifier (name ou id)
         * @param string value Nouvelle valeur du champ à modifier
         */
        function update($key, $value) {

            for($i=0; $i<sizeof($this->exifData); $i++) {
                if( strtolower($this->exifData[$i]['name']) == strtolower($key) || strtolower($this->exifData[$i]['id']) == strtolower($key) ) {
                    $this->exifData[$i]['value'] = $value;
                    return;
                }
            }

            $i = self::find(self::$EXIF_IDS, 'name', $key);

            if($i <= 0)
                $i = self::find(self::$EXIF_IDS, 'id', $key);

            if($i >= 0) {
                $array = self::$EXIF_IDS[$i];
                $array['value'] = $value;

                $this->exifData[] = $array;
            }
        }

        /** Trouve l'index de l'objet recherché
         *
         * @param array  $array Liste de liste associative
         * @param string $key   Champ de recherche
         * @param string $value Valeur du champ recherché
         *
         * @param integer Index de l'objet
         */
        private function find($array, $key, $value) {
            for($i=0; $i<sizeof($array); $i++) {
                if( strtolower( $array[$i][$key] ) == strtolower($value) )
                    return $i;
            }
            return -1;
        }

        /** Converti une chaine de caractère pour Windows ou l'inverse
         *
         * @param string  $input    Chaine de caractères à transformer
         * @param boolean $reverse Optionnel : fait la transformation inverse
         *
         * @return string Chaine de caractères transformée
         */
        function windowsFormat($input, $reverse = false) {

            if(!$reverse) {
                $array = array();

                for($i=0; $i<strlen($input); $i++) {
                    $array[] = ord($input[$i]);
                    $array[] = 0;
                }

                $array[] = 0;
                $array[] = 0;

                return join(', ', $array);

            } else {
                $array = explode(', ', $input);
                $string = '';

                foreach($array as $element) {
                    if($element != 0)
                        $string .= chr($element);
                }

                return $string;
            }
        }

        /** Transforme les métadonnées en array
         *
         * @param string $filename Le chemin vers le fichier concerné
         *
         * @return array Liste des différents paramètres
         */
        private function chunk_image($filename) {

            $image_array       = array();
            $image_data        = file_get_contents($filename);
            $image_data_length = strlen($image_data);

            for ($i = 0; $i < $image_data_length; $i += 2) {
                if (ord(substr($image_data, $i + 1, 1)) < 0xD0 || ord(substr($image_data, $i + 1, 1)) > 0xD7) {
                    $segment_type  = substr($image_data, $i + 3, 1);
                    $segment_type  = ord($segment_type);
                    $segment_size  = substr($image_data, $i + 4, 2);
                    $segment_size  = unpack('n', $segment_size);
                    $segment_size  = array_pop($segment_size);
                    $segment_data  = substr($image_data, $i + 6, $segment_size - 2);
                    $image_array[] = array(
                        'type' => $segment_type,
                        'value' => $segment_data
                    );
                    $i += $segment_size;
                    if ($segment_type == 0xDA) {
                        $end_of_image  = strpos($image_data, "\xFF\xD9");
                        $raw_image     = substr($image_data, $i + 4, $end_of_image - ($i + 4));
                        $image_array[] = array(
                            'type' => 'raw_image',
                            'value' => $raw_image
                        );
                        break;
                    }
                }
            }

            return $image_array;
        }

        /** Sauvegarde les modifications
         *
         * @param string $newFilename Paramètre optionnel pour spécifier le nom du nouveau fichier
         */
        function save($newFilename = '') {

            if( $newFilename == '' )
                $newFilename = $this->filename;

            $exifDataUpdate = $this->exifData;

            for($i=0; $i<sizeof($exifDataUpdate); $i++) {
                if( isset($exifDataUpdate[$i]['windows']) && $exifDataUpdate[$i]['windows'] )
                    $exifDataUpdate[$i]['value'] = self::windowsFormat($exifDataUpdate[$i]['value']);
            }

            $exif  = '';
            $exif .= 'Exif';
            $exif .= "\x00\x00";
            $exif .= 'MM';
            $exif .= pack('n', 42);
            $exif .= pack('N', 8);
            $exif .= pack('n', count($exifDataUpdate));
            $segment_length = 2 + count($exifDataUpdate) * 12 + 4;
            $segment_head   = '';
            $segment_body   = '';

            foreach ($exifDataUpdate as $row) {

                $segment_head .= pack('n', $row['type']);
                $segment_head .= pack('n', 2);
                $data = $row['value'] . "\x00";
                $data = str_pad($data, 4, "\x00");
                $segment_head .= pack('N', strlen($data));
                if (strlen($data) > 4) {
                    $offset = 8 + $segment_length + strlen($segment_body);
                    $segment_head .= pack('N', $offset);
                    $segment_body .= $data;
                } else
                    $segment_head .= $data;

            }

            $exif .= $segment_head;
            $exif .= pack('N', 0);
            $exif .= $segment_body;

            $image_array = self::chunk_image($this->filename);
            foreach ($image_array as $key => $row) {
                if ($row['type'] == 0xE0 || $row['type'] == 0xFE)
                    unset($image_array[$key]);
            }

            array_unshift($image_array, array(
                'type' => 0xE1,
                'value' => $exif
            ));

            $new_image = "\xFF" . "\xD8";
            foreach ($image_array as $row) {
                if ($row['type'] == 'raw_image') {
                    $compressed_image_data = $row['value'];
                    continue;
                }
                $new_image .= sprintf("\xFF%c", $row['type']);
                $new_image .= pack('n', strlen($row['value']) + 2);
                $new_image .= $row['value'];
            }

            $new_image .= $compressed_image_data;
            $new_image .= "\xFF" . "\xD9";

            file_put_contents($newFilename, $new_image);
        }

    }
?>
