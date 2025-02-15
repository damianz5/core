<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Core\FieldType\Validator;

use Ibexa\Core\FieldType\ValidationError;
use Ibexa\Core\FieldType\Validator;
use Ibexa\Core\FieldType\Value;

class ImageValidator extends Validator
{
    /**
     * {@inheritdoc}
     */
    public function validateConstraints($constraints)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Value $value)
    {
        $isValid = true;
        if (isset($value->inputUri) && !$this->innerValidate($value->inputUri)) {
            $isValid = false;
        }

        // BC: Check if file is a valid image if the value of 'id' matches a local file
        if (isset($value->id) && file_exists($value->id) && !$this->innerValidate($value->id)) {
            $isValid = false;
        }

        return $isValid;
    }

    private function innerValidate($filePath)
    {
        // silence `getimagesize` error as extension-wise valid image files might produce it anyway
        // note that file extension checking is done using other validation which should be called before this one
        if (!@getimagesize($filePath)) {
            $this->errors[] = new ValidationError(
                'A valid image file is required.',
                null,
                [],
                'id'
            );

            return false;
        }

        return true;
    }
}

class_alias(ImageValidator::class, 'eZ\Publish\Core\FieldType\Validator\ImageValidator');
