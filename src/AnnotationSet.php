<?php

    namespace b2db;

    /**
     * Annotation set class
     *
     * @package b2db
     */
    class AnnotationSet
    {

        protected const IGNORED_ANNOTATIONS = [
            'var',
            'access',
            'package',
            'subpackage',
            'author',
            'license',
            'version',
            'copyright'
        ];

        /**
         * @var array<string, Annotation>
         */
        protected array $_annotations = [];

        /**
         * AnnotationSet constructor.
         * @param string $docblock
         */
        public function __construct(string $docblock)
        {
            $docblock = str_replace("\r\n", "\n", $docblock);
            $docblock_length = strlen($docblock);

            $current_annotation = null;
            $annotations = [];

            for ($i = 0; $i < $docblock_length; $i++) {
                $character = $docblock[$i];
                if ($current_annotation === null && $character === '@') {
                    $current_annotation = '';
                    $current_annotation_data = '';
                    $i++;
                    while ($i <= $docblock_length) {
                        $character = $docblock[$i];
                        if (in_array($character, ["\n", " ", "("], true)) {
                            break;
                        }

                        $current_annotation .= $character;
                        $i++;
                    }
                    if ($character === '(') {
                        $i++;
                        while ($i <= $docblock_length) {
                            if (!isset($docblock[$i])) {
                                throw new Exception('Cannot parse annotation: ' . $docblock);
                            }
                            $character = $docblock[$i];
                            if (in_array($character, array(")", '@'))) {
                                break;
                            }

                            $current_annotation_data .= $character;
                            $i++;
                        }
                    }
                    $current_annotation = trim($current_annotation);
                    if (!in_array($current_annotation, self::IGNORED_ANNOTATIONS)) {
                        $annotations[$current_annotation] = new Annotation($current_annotation, $current_annotation_data);
                    }
                    $current_annotation = null;
                }
            }
            $this->_annotations = $annotations;
        }

        /**
         * Check to see if a specified annotation exists
         * @param string $annotation
         * @return bool
         */
        public function hasAnnotation(string $annotation): bool
        {
            return array_key_exists($annotation, $this->_annotations);
        }

        /**
         * Returns the specified annotation
         * @param string $annotation
         * @return ?Annotation
         */
        public function getAnnotation(string $annotation): ?Annotation
        {
            return ($this->hasAnnotation($annotation)) ? $this->_annotations[$annotation] : null;
        }

        /**
         * Returns all annotations
         * @return Annotation[]
         */
        public function getAnnotations(): array
        {
            return $this->_annotations;
        }

        /**
         * Return the number of annotations in this annotation set
         * @return int
         */
        public function count(): int
        {
            return count($this->_annotations);
        }

        /**
         * Return whether or not this annotation set has any annotations
         * @return bool
         */
        public function hasAnnotations(): bool
        {
            return (bool) $this->count();
        }

    }
