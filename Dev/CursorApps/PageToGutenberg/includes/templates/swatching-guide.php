<?php
/**
 * Template for the swatching guide post.
 * 
 * @package UTG
 */

namespace UTG\Templates;

/**
 * Get the swatching guide template content
 *
 * @return array Template data with title and blocks
 */
function get_swatching_guide_template() {
    return [
        'title' => 'Guide to Swatching: Your Key to Successful Knitting Projects',
        'blocks' => [
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'Not all projects are worked flat, which means your gauge might be different when knitting in the round. This is especially true for knitters whose purl tension differs from their knit tension.',
                    'dropCap' => false
                ]
            ],
            [
                'type' => 'core/image',
                'attributes' => [
                    'url' => 'https://stratus.campaign-image.com/images/777193000034649004_zc_v1_1741624924808_image10_(4).jpg',
                    'alt' => 'Knitting gauge swatch example',
                    'caption' => '© Quince & Co'
                ]
            ],
            [
                'type' => 'core/heading',
                'attributes' => [
                    'content' => 'Speed Swatch Method',
                    'level' => 2
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'To swatch in the round without knitting an entire tube, try the speed swatch method. Some knitters prefer to cut the floats in the back before blocking so the swatch lays completely flat, but if your floats are loose and your swatch is large enough, you won\'t need to cut them.'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'This way, you can unravel the swatch and use the yarn in your project if you find yourself playing yarn chicken later.'
                ]
            ],
            [
                'type' => 'core/image',
                'attributes' => [
                    'url' => 'https://stratus.campaign-image.com/images/777193000034649004_zc_v1_1741624944842_image2_(9).jpg',
                    'alt' => 'Knitting swatch measurement',
                    'caption' => '© Wiikymossroots'
                ]
            ],
            [
                'type' => 'core/heading',
                'attributes' => [
                    'content' => 'Why Swatching Matters',
                    'level' => 2
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'A gauge swatch isn\'t just about stitch count—it\'s also about how the fabric feels. A swatch helps you decide if the fabric is sturdy enough for a tote bag, drapey enough for a shawl, or structured enough for a sweater.'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'Think of swatching as a first date with your fabric—you\'re figuring out if this is something you want to commit to before investing time and effort into a full project.'
                ]
            ],
            [
                'type' => 'core/heading',
                'attributes' => [
                    'content' => 'How to Swatch for Different Stitch Patterns',
                    'level' => 2
                ]
            ],
            [
                'type' => 'core/heading',
                'attributes' => [
                    'content' => 'Stockinette Swatch',
                    'level' => 3
                ]
            ],
            [
                'type' => 'core/image',
                'attributes' => [
                    'url' => 'https://stratus.campaign-image.com/images/777193000034649004_zc_v1_1741624958535_image4_(5).jpg',
                    'alt' => 'Stockinette stitch swatch',
                    'caption' => '© A Yarn Story'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'Cast on enough stitches for at least a 4-inch wide section and knit in stockinette stitch (knit on the right side, purl on the wrong side). If swatching for a project knit in the round, use the method described above to mimic your in-the-round tension.'
                ]
            ],
            [
                'type' => 'core/heading',
                'attributes' => [
                    'content' => 'Lace Swatch',
                    'level' => 3
                ]
            ],
            [
                'type' => 'core/image',
                'attributes' => [
                    'url' => 'https://stratus.campaign-image.com/images/777193000034649004_zc_v1_1741624968919_image5_(4).jpg',
                    'alt' => 'Lace knitting swatch',
                    'caption' => '© SweetGeorgia Yarns'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'Swatch in the lace pattern provided in the pattern, and always block before measuring. Lace stitches open up significantly after blocking. If you\'re substituting yarn, swatching will help you decide if your chosen yarn holds lace definition well.'
                ]
            ],
            [
                'type' => 'core/heading',
                'attributes' => [
                    'content' => 'Cable Swatch',
                    'level' => 3
                ]
            ],
            [
                'type' => 'core/image',
                'attributes' => [
                    'url' => 'https://stratus.campaign-image.com/images/777193000034649004_zc_v1_1741624982145_image1_(5).jpg',
                    'alt' => 'Cable knitting swatch',
                    'caption' => '© Sheep Among Wolves'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'Cables pull fabric inward, making it denser than stockinette. If your pattern includes cables, always swatch in pattern to get an accurate gauge. Expect the width to shrink compared to stockinette.'
                ]
            ],
            [
                'type' => 'core/heading',
                'attributes' => [
                    'content' => 'Colorwork Swatch',
                    'level' => 3
                ]
            ],
            [
                'type' => 'core/image',
                'attributes' => [
                    'url' => 'https://stratus.campaign-image.com/images/777193000034649004_zc_v1_1741625016562_image3_(4).jpg',
                    'alt' => 'Colorwork knitting swatch',
                    'caption' => '© Quince & Co'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'Work the colorwork section of your pattern in the round or flat according to your pattern, and block before measuring. Colorwork stitches can be tighter than plain knitting, and blocking helps even out the fabric.'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'Swatching might not be the most exciting part of knitting, but it\'s the step that saves future-you from frustration. A little time spent upfront means no surprises when your sweater actually fits, your lacework blocks beautifully, and your colorwork doesn\'t turn into an accidental compression sleeve.'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => 'So grab some yarn, cast on a swatch, and see what your stitches are telling you. Testing out a new fiber, checking gauge, or getting to know your fabric before committing—it\'s all time well spent.'
                ]
            ],
            [
                'type' => 'core/paragraph',
                'attributes' => [
                    'content' => '(And if you\'ve ever skipped it and regretted it later—we\'ve all been there.)\n\nHappy knitting – and happy swatching!'
                ]
            ]
        ]
    ];
} 