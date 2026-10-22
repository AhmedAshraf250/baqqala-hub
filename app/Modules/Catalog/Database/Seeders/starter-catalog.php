<?php

/*
|--------------------------------------------------------------------------
| The starter catalog
|--------------------------------------------------------------------------
|
| A general grocery's shelves, for a shop to start from: installed once with
| `php artisan app:catalog:install-starter`, then edited, archived, and added
| to from the products screen. The prices are samples — review every one
| before using them.
|
| Each key is the record's slug and never changes: the command adds the keys
| that are not in the database yet and leaves every existing one exactly as
| the owner left it, so running it again after adding lines here adds those
| lines only.
|
| unit: piece | kg | liter. price: per unit, as a decimal string.
| terms: other names the counter types for the product.
|
*/

return [

    'rice-pasta' => [
        'name' => 'أرز ومكرونة',
        'icon' => 'basket',
        'products' => [
            ['key' => 'rice-loose', 'name' => 'أرز مصري سائب', 'unit' => 'kg', 'price' => '32.00', 'terms' => 'رز فرط'],
            ['key' => 'rice-doha-1kg', 'name' => 'أرز الضحى 1 كجم', 'unit' => 'piece', 'price' => '38.00', 'terms' => 'رز ضحي doha'],
            ['key' => 'rice-doha-5kg', 'name' => 'أرز الضحى 5 كجم', 'unit' => 'piece', 'price' => '150.00', 'terms' => 'رز ضحي doha'],
            ['key' => 'rice-basmati-1kg', 'name' => 'أرز بسمتي أبو كاس 1 كجم', 'unit' => 'piece', 'price' => '115.00', 'terms' => 'رز بسمتي'],
            ['key' => 'pasta-malika-penne', 'name' => 'مكرونة الملكة قلم 400 جم', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'معكرونة'],
            ['key' => 'pasta-malika-spaghetti', 'name' => 'مكرونة الملكة اسباجتي 400 جم', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'معكرونة سباجيتي'],
            ['key' => 'pasta-regina-1kg', 'name' => 'مكرونة ريجينا 1 كجم', 'unit' => 'piece', 'price' => '42.00', 'terms' => 'معكرونة regina'],
            ['key' => 'pasta-loose', 'name' => 'مكرونة سائبة', 'unit' => 'kg', 'price' => '30.00', 'terms' => 'معكرونة فرط'],
            ['key' => 'vermicelli-400g', 'name' => 'شعرية 400 جم', 'unit' => 'piece', 'price' => '14.00'],
            ['key' => 'orzo-400g', 'name' => 'لسان عصفور 400 جم', 'unit' => 'piece', 'price' => '15.00'],
        ],
    ],

    'legumes' => [
        'name' => 'بقوليات وحبوب',
        'icon' => 'flower1',
        'products' => [
            ['key' => 'lentils-yellow-loose', 'name' => 'عدس أصفر سائب', 'unit' => 'kg', 'price' => '55.00'],
            ['key' => 'lentils-brown-loose', 'name' => 'عدس بجبة سائب', 'unit' => 'kg', 'price' => '48.00'],
            ['key' => 'lentils-yellow-1kg', 'name' => 'عدس أصفر 1 كجم', 'unit' => 'piece', 'price' => '60.00'],
            ['key' => 'fava-dry-loose', 'name' => 'فول ناشف سائب', 'unit' => 'kg', 'price' => '45.00', 'terms' => 'فول بلدي'],
            ['key' => 'white-beans', 'name' => 'فاصوليا بيضاء', 'unit' => 'kg', 'price' => '70.00', 'terms' => 'فاصوليا ناشفة'],
            ['key' => 'black-eyed-peas', 'name' => 'لوبيا', 'unit' => 'kg', 'price' => '65.00'],
            ['key' => 'chickpeas', 'name' => 'حمص الشام', 'unit' => 'kg', 'price' => '80.00', 'terms' => 'حمص'],
            ['key' => 'wheat-belila', 'name' => 'قمح بليلة', 'unit' => 'kg', 'price' => '30.00'],
            ['key' => 'freekeh', 'name' => 'فريك', 'unit' => 'kg', 'price' => '60.00'],
            ['key' => 'bulgur', 'name' => 'برغل', 'unit' => 'kg', 'price' => '40.00'],
        ],
    ],

    'sugar-flour' => [
        'name' => 'سكر ودقيق',
        'icon' => 'bag',
        'products' => [
            ['key' => 'sugar-loose', 'name' => 'سكر أبيض سائب', 'unit' => 'kg', 'price' => '36.00', 'terms' => 'سكر فرط'],
            ['key' => 'sugar-1kg', 'name' => 'سكر 1 كجم', 'unit' => 'piece', 'price' => '38.00'],
            ['key' => 'flour-1kg', 'name' => 'دقيق فاخر 1 كجم', 'unit' => 'piece', 'price' => '28.00', 'terms' => 'طحين'],
            ['key' => 'flour-loose', 'name' => 'دقيق سائب', 'unit' => 'kg', 'price' => '24.00', 'terms' => 'طحين فرط'],
            ['key' => 'starch-250g', 'name' => 'نشا 250 جم', 'unit' => 'piece', 'price' => '10.00'],
            ['key' => 'baking-powder', 'name' => 'بيكنج باودر', 'unit' => 'piece', 'price' => '5.00'],
            ['key' => 'vanilla', 'name' => 'فانيليا', 'unit' => 'piece', 'price' => '2.00'],
            ['key' => 'instant-yeast', 'name' => 'خميرة فورية', 'unit' => 'piece', 'price' => '5.00'],
        ],
    ],

    'oils-ghee' => [
        'name' => 'زيوت وسمنة',
        'icon' => 'droplet',
        'products' => [
            ['key' => 'oil-crystal-1l', 'name' => 'زيت كريستال 1 لتر', 'unit' => 'piece', 'price' => '90.00', 'terms' => 'crystal كرستال'],
            ['key' => 'oil-afia-1l', 'name' => 'زيت عافية 1 لتر', 'unit' => 'piece', 'price' => '105.00', 'terms' => 'afia'],
            ['key' => 'oil-afia-2-25l', 'name' => 'زيت عافية 2.25 لتر', 'unit' => 'piece', 'price' => '230.00', 'terms' => 'afia'],
            ['key' => 'oil-hala-1l', 'name' => 'زيت خليط هلا 1 لتر', 'unit' => 'piece', 'price' => '80.00'],
            ['key' => 'oil-loose', 'name' => 'زيت سائب', 'unit' => 'liter', 'price' => '75.00', 'terms' => 'زيت فرط'],
            ['key' => 'olive-oil-500ml', 'name' => 'زيت زيتون 500 مل', 'unit' => 'piece', 'price' => '180.00'],
            ['key' => 'ghee-crystal-1-5kg', 'name' => 'سمنة كريستال 1.5 كجم', 'unit' => 'piece', 'price' => '160.00', 'terms' => 'crystal'],
            ['key' => 'ghee-hanem-2kg', 'name' => 'سمنة الهانم 2 كجم', 'unit' => 'piece', 'price' => '210.00'],
            ['key' => 'ghee-baladi', 'name' => 'سمنة بلدي', 'unit' => 'kg', 'price' => '300.00', 'terms' => 'سمن'],
        ],
    ],

    'tea-coffee' => [
        'name' => 'شاي وقهوة',
        'icon' => 'cup-hot',
        'products' => [
            ['key' => 'tea-arousa-250g', 'name' => 'شاي العروسة 250 جم', 'unit' => 'piece', 'price' => '75.00', 'terms' => 'عروسه'],
            ['key' => 'tea-arousa-40-bags', 'name' => 'شاي العروسة 40 فتلة', 'unit' => 'piece', 'price' => '45.00', 'terms' => 'عروسه فتل'],
            ['key' => 'tea-lipton-100-bags', 'name' => 'شاي ليبتون 100 فتلة', 'unit' => 'piece', 'price' => '120.00', 'terms' => 'lipton فتل'],
            ['key' => 'tea-ahmad-250g', 'name' => 'شاي أحمد 250 جم', 'unit' => 'piece', 'price' => '85.00', 'terms' => 'ahmad'],
            ['key' => 'tea-loose', 'name' => 'شاي سائب', 'unit' => 'kg', 'price' => '280.00', 'terms' => 'شاي فرط'],
            ['key' => 'coffee-loose', 'name' => 'بن محوج سائب', 'unit' => 'kg', 'price' => '600.00', 'terms' => 'قهوة تركي'],
            ['key' => 'nescafe-classic-sachet', 'name' => 'نسكافيه كلاسيك (كيس)', 'unit' => 'piece', 'price' => '5.00', 'terms' => 'nescafe قهوة سريعة'],
            ['key' => 'nescafe-3in1', 'name' => 'نسكافيه 3×1', 'unit' => 'piece', 'price' => '6.00', 'terms' => 'nescafe قهوة'],
            ['key' => 'cocoa-100g', 'name' => 'كاكاو 100 جم', 'unit' => 'piece', 'price' => '35.00'],
            ['key' => 'anise-20-bags', 'name' => 'ينسون 20 فتلة', 'unit' => 'piece', 'price' => '25.00', 'terms' => 'يانسون'],
        ],
    ],

    'dairy-eggs' => [
        'name' => 'ألبان وبيض',
        'icon' => 'egg',
        'products' => [
            ['key' => 'milk-juhayna-1l', 'name' => 'لبن جهينة 1 لتر', 'unit' => 'piece', 'price' => '45.00', 'terms' => 'juhayna حليب'],
            ['key' => 'milk-juhayna-500ml', 'name' => 'لبن جهينة 500 مل', 'unit' => 'piece', 'price' => '24.00', 'terms' => 'juhayna حليب'],
            ['key' => 'milk-almarai-1l', 'name' => 'لبن المراعي 1 لتر', 'unit' => 'piece', 'price' => '46.00', 'terms' => 'almarai حليب'],
            ['key' => 'yogurt-juhayna', 'name' => 'زبادي جهينة', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'juhayna'],
            ['key' => 'yogurt-danone', 'name' => 'زبادي دانون', 'unit' => 'piece', 'price' => '11.00', 'terms' => 'danone'],
            ['key' => 'cheese-domty-feta-500g', 'name' => 'جبنة دومتي فيتا 500 جم', 'unit' => 'piece', 'price' => '45.00', 'terms' => 'domty'],
            ['key' => 'cheese-white-loose', 'name' => 'جبنة بيضا سايبة', 'unit' => 'kg', 'price' => '140.00', 'terms' => 'جبنة بيضاء فرط'],
            ['key' => 'cheese-roumy-loose', 'name' => 'جبنة رومي سايبة', 'unit' => 'kg', 'price' => '380.00', 'terms' => 'رومي فرط'],
            ['key' => 'cheese-la-vache-qui-rit-8', 'name' => 'جبنة لافاش كيري 8 قطع', 'unit' => 'piece', 'price' => '45.00', 'terms' => 'البقرة الضاحكة لافاشكيري'],
            ['key' => 'butter-200g', 'name' => 'زبدة 200 جم', 'unit' => 'piece', 'price' => '75.00'],
            ['key' => 'cream-200g', 'name' => 'قشطة 200 جم', 'unit' => 'piece', 'price' => '30.00'],
            ['key' => 'egg-single', 'name' => 'بيض (البيضة)', 'unit' => 'piece', 'price' => '6.50'],
            ['key' => 'eggs-tray-30', 'name' => 'طبق بيض 30', 'unit' => 'piece', 'price' => '190.00', 'terms' => 'كرتونة بيض'],
        ],
    ],

    'drinks' => [
        'name' => 'مياه ومشروبات',
        'icon' => 'cup-straw',
        'products' => [
            ['key' => 'water-nestle-1-5l', 'name' => 'مياه نستله 1.5 لتر', 'unit' => 'piece', 'price' => '9.00', 'terms' => 'nestle مية'],
            ['key' => 'water-aquafina-600ml', 'name' => 'مياه أكوافينا 600 مل', 'unit' => 'piece', 'price' => '6.00', 'terms' => 'aquafina مية'],
            ['key' => 'water-carton-1-5l-6', 'name' => 'كرتونة مياه 1.5 لتر × 6', 'unit' => 'piece', 'price' => '52.00', 'terms' => 'مية'],
            ['key' => 'pepsi-1l', 'name' => 'بيبسي 1 لتر', 'unit' => 'piece', 'price' => '22.00', 'terms' => 'pepsi بيبس'],
            ['key' => 'pepsi-can', 'name' => 'بيبسي كانز', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'pepsi علبة'],
            ['key' => 'coca-cola-1l', 'name' => 'كوكاكولا 1 لتر', 'unit' => 'piece', 'price' => '22.00', 'terms' => 'كوكا كولا coca cola'],
            ['key' => 'sprite-1l', 'name' => 'سبرايت 1 لتر', 'unit' => 'piece', 'price' => '22.00', 'terms' => 'sprite'],
            ['key' => 'schweppes-pomegranate-can', 'name' => 'شويبس رمان كانز', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'schweppes'],
            ['key' => 'red-bull', 'name' => 'ريد بول', 'unit' => 'piece', 'price' => '50.00', 'terms' => 'red bull طاقة'],
            ['key' => 'power-horse', 'name' => 'باور هورس', 'unit' => 'piece', 'price' => '35.00', 'terms' => 'power horse طاقة'],
        ],
    ],

    'juices' => [
        'name' => 'عصائر',
        'icon' => 'cup',
        'products' => [
            ['key' => 'juice-juhayna-mango-1l', 'name' => 'عصير جهينة مانجو 1 لتر', 'unit' => 'piece', 'price' => '40.00', 'terms' => 'juhayna منجا'],
            ['key' => 'juice-juhayna-235ml', 'name' => 'عصير جهينة 235 مل', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'juhayna'],
            ['key' => 'juice-beyti-guava-1l', 'name' => 'عصير بيتي جوافة 1 لتر', 'unit' => 'piece', 'price' => '38.00', 'terms' => 'beyti'],
            ['key' => 'juice-beyti-orange-1l', 'name' => 'عصير بيتي برتقال 1 لتر', 'unit' => 'piece', 'price' => '38.00', 'terms' => 'beyti'],
            ['key' => 'juice-beyti-200ml', 'name' => 'عصير بيتي 200 مل', 'unit' => 'piece', 'price' => '8.00', 'terms' => 'beyti'],
            ['key' => 'tang-orange-sachet', 'name' => 'تانج برتقال (كيس)', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'tang'],
        ],
    ],

    'ice-cream' => [
        'name' => 'آيس كريم وجيلاتي',
        'icon' => 'snow',
        'products' => [
            ['key' => 'ice-cream-vanilla-cup', 'name' => 'آيس كريم كوب فانيليا', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'ايس كريم'],
            ['key' => 'ice-cream-chocolate-cone', 'name' => 'آيس كريم كون شوكولاتة', 'unit' => 'piece', 'price' => '20.00', 'terms' => 'ايس كريم'],
            ['key' => 'ice-cream-sandwich', 'name' => 'آيس كريم ساندوتش بسكويت', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'ايس كريم'],
            ['key' => 'ice-cream-chocolate-stick', 'name' => 'آيس كريم ستيك شوكولاتة', 'unit' => 'piece', 'price' => '18.00', 'terms' => 'ايس كريم'],
            ['key' => 'ice-lolly-fruit', 'name' => 'مصاصة فواكه', 'unit' => 'piece', 'price' => '7.00'],
            ['key' => 'gelato-1l', 'name' => 'جيلاتي علبة 1 لتر', 'unit' => 'piece', 'price' => '85.00', 'terms' => 'جيلاتو'],
        ],
    ],

    'biscuits-sweets' => [
        'name' => 'بسكويت وحلويات',
        'icon' => 'cookie',
        'products' => [
            ['key' => 'biscuit-shamadan', 'name' => 'بسكويت شمعدان', 'unit' => 'piece', 'price' => '5.00'],
            ['key' => 'biscuit-abu-walad', 'name' => 'بسكويت أبو ولد', 'unit' => 'piece', 'price' => '5.00'],
            ['key' => 'biscuit-marie', 'name' => 'بسكويت ماري', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'marie'],
            ['key' => 'biscuit-oreo', 'name' => 'بسكويت أوريو', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'oreo'],
            ['key' => 'biscuit-dates', 'name' => 'بسكويت بالتمر', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'معمول'],
            ['key' => 'cake-todo', 'name' => 'كيك تودو', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'todo كيكة'],
            ['key' => 'wafer-coco', 'name' => 'ويفر كوكو', 'unit' => 'piece', 'price' => '5.00', 'terms' => 'wafer'],
            ['key' => 'chocolate-cadbury-30g', 'name' => 'شوكولاتة كادبوري 30 جم', 'unit' => 'piece', 'price' => '20.00', 'terms' => 'cadbury شيكولاتة'],
            ['key' => 'chocolate-galaxy', 'name' => 'شوكولاتة جالكسي', 'unit' => 'piece', 'price' => '25.00', 'terms' => 'galaxy شيكولاتة'],
            ['key' => 'halawa-250g', 'name' => 'حلاوة طحينية 250 جم', 'unit' => 'piece', 'price' => '40.00', 'terms' => 'حلاوه'],
        ],
    ],

    'chips-snacks' => [
        'name' => 'شيبسي وسناكس',
        'icon' => 'bag-heart',
        'products' => [
            ['key' => 'chipsy-small', 'name' => 'شيبسي صغير', 'unit' => 'piece', 'price' => '5.00', 'terms' => 'chipsy شبسي شيبس'],
            ['key' => 'chipsy-family', 'name' => 'شيبسي عائلي', 'unit' => 'piece', 'price' => '20.00', 'terms' => 'chipsy شبسي شيبس كبير'],
            ['key' => 'tiger-small', 'name' => 'تايجر صغير', 'unit' => 'piece', 'price' => '5.00', 'terms' => 'tiger شيبس'],
            ['key' => 'doritos', 'name' => 'دوريتوس', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'doritos'],
            ['key' => 'cheetos', 'name' => 'شيتوس', 'unit' => 'piece', 'price' => '5.00', 'terms' => 'cheetos'],
            ['key' => 'bake-rolz', 'name' => 'بيك رولز', 'unit' => 'piece', 'price' => '7.00', 'terms' => 'bake rolz'],
            ['key' => 'popcorn-salted', 'name' => 'فشار مملح', 'unit' => 'piece', 'price' => '5.00', 'terms' => 'popcorn'],
        ],
    ],

    'seeds-nuts' => [
        'name' => 'لب ومكسرات',
        'icon' => 'flower2',
        'products' => [
            ['key' => 'seeds-white-loose', 'name' => 'لب أبيض سائب', 'unit' => 'kg', 'price' => '180.00', 'terms' => 'لب فرط'],
            ['key' => 'seeds-syrian-loose', 'name' => 'لب سوري سائب', 'unit' => 'kg', 'price' => '220.00', 'terms' => 'لب فرط'],
            ['key' => 'seeds-brown-loose', 'name' => 'لب أسمر سائب', 'unit' => 'kg', 'price' => '150.00', 'terms' => 'لب فرط'],
            ['key' => 'peanuts-roasted', 'name' => 'سوداني محمص', 'unit' => 'kg', 'price' => '120.00', 'terms' => 'فول سوداني'],
            ['key' => 'chickpeas-roasted', 'name' => 'حمص محمص', 'unit' => 'kg', 'price' => '100.00'],
            ['key' => 'seeds-white-small-bag', 'name' => 'لب أبيض كيس صغير', 'unit' => 'piece', 'price' => '5.00'],
            ['key' => 'mixed-nuts', 'name' => 'مكسرات مشكلة', 'unit' => 'kg', 'price' => '450.00', 'terms' => 'مكسرات'],
        ],
    ],

    'canned' => [
        'name' => 'معلبات',
        'icon' => 'box-seam',
        'products' => [
            ['key' => 'foul-americana', 'name' => 'فول مدمس أمريكانا', 'unit' => 'piece', 'price' => '18.00', 'terms' => 'americana فول علبة'],
            ['key' => 'foul-tahini', 'name' => 'فول مدمس بالطحينة', 'unit' => 'piece', 'price' => '20.00', 'terms' => 'فول علبة'],
            ['key' => 'tuna-sunshine-140g', 'name' => 'تونة صن شاين 140 جم', 'unit' => 'piece', 'price' => '45.00', 'terms' => 'sunshine تونه'],
            ['key' => 'tuna-dolphin', 'name' => 'تونة دولفين', 'unit' => 'piece', 'price' => '40.00', 'terms' => 'dolphin تونه'],
            ['key' => 'sardines', 'name' => 'سردين', 'unit' => 'piece', 'price' => '30.00'],
            ['key' => 'peas-canned', 'name' => 'بسلة معلبة', 'unit' => 'piece', 'price' => '20.00', 'terms' => 'بازلاء'],
            ['key' => 'sweet-corn-canned', 'name' => 'ذرة حلوة معلبة', 'unit' => 'piece', 'price' => '30.00'],
            ['key' => 'jam-strawberry-400g', 'name' => 'مربى فراولة 400 جم', 'unit' => 'piece', 'price' => '40.00', 'terms' => 'مربي'],
            ['key' => 'honey-500g', 'name' => 'عسل نحل 500 جم', 'unit' => 'piece', 'price' => '120.00'],
            ['key' => 'molasses-700g', 'name' => 'عسل أسود 700 جم', 'unit' => 'piece', 'price' => '35.00'],
        ],
    ],

    'sauces-spices' => [
        'name' => 'صلصات وتوابل',
        'icon' => 'fire',
        'products' => [
            ['key' => 'tomato-paste-320g', 'name' => 'صلصة طماطم 320 جم', 'unit' => 'piece', 'price' => '18.00', 'terms' => 'صلصه معجون طماطم'],
            ['key' => 'ketchup-heinz', 'name' => 'كاتشب هاينز', 'unit' => 'piece', 'price' => '35.00', 'terms' => 'heinz كتشب'],
            ['key' => 'mayonnaise-goody', 'name' => 'مايونيز جودي', 'unit' => 'piece', 'price' => '30.00', 'terms' => 'goody مايونيزه'],
            ['key' => 'tahini-300g', 'name' => 'طحينة 300 جم', 'unit' => 'piece', 'price' => '45.00'],
            ['key' => 'stock-maggi-cube', 'name' => 'مرقة ماجي (مكعب)', 'unit' => 'piece', 'price' => '3.00', 'terms' => 'maggi'],
            ['key' => 'stock-knorr-chicken-8', 'name' => 'مرقة كنور فراخ 8 مكعبات', 'unit' => 'piece', 'price' => '25.00', 'terms' => 'knorr'],
            ['key' => 'cumin-loose', 'name' => 'كمون سائب', 'unit' => 'kg', 'price' => '400.00'],
            ['key' => 'black-pepper-loose', 'name' => 'فلفل أسود سائب', 'unit' => 'kg', 'price' => '600.00'],
            ['key' => 'mixed-spices-bag', 'name' => 'بهارات مشكلة (كيس)', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'بهار'],
            ['key' => 'salt-1kg', 'name' => 'ملح طعام 1 كجم', 'unit' => 'piece', 'price' => '10.00'],
            ['key' => 'vinegar-1l', 'name' => 'خل 1 لتر', 'unit' => 'piece', 'price' => '12.00'],
            ['key' => 'hot-sauce', 'name' => 'صلصة حارة', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'شطة'],
        ],
    ],

    'cleaning' => [
        'name' => 'منظفات ومساحيق',
        'icon' => 'bucket',
        'products' => [
            ['key' => 'ariel-automatic-2-5kg', 'name' => 'أريال أوتوماتيك 2.5 كجم', 'unit' => 'piece', 'price' => '280.00', 'terms' => 'ariel مسحوق'],
            ['key' => 'persil-1kg', 'name' => 'برسيل 1 كجم', 'unit' => 'piece', 'price' => '110.00', 'terms' => 'persil مسحوق'],
            ['key' => 'tide-hand-1kg', 'name' => 'تايد يدوي 1 كجم', 'unit' => 'piece', 'price' => '85.00', 'terms' => 'tide مسحوق'],
            ['key' => 'fairy-650ml', 'name' => 'فيري سائل أطباق 650 مل', 'unit' => 'piece', 'price' => '45.00', 'terms' => 'fairy صابون مواعين'],
            ['key' => 'pril-1l', 'name' => 'بريل 1 لتر', 'unit' => 'piece', 'price' => '40.00', 'terms' => 'pril صابون مواعين'],
            ['key' => 'clorox-1l', 'name' => 'كلوركس 1 لتر', 'unit' => 'piece', 'price' => '30.00', 'terms' => 'clorox كلور'],
            ['key' => 'dettol-500ml', 'name' => 'ديتول 500 مل', 'unit' => 'piece', 'price' => '95.00', 'terms' => 'dettol مطهر'],
            ['key' => 'floor-cleaner-1l', 'name' => 'منظف أرضيات 1 لتر', 'unit' => 'piece', 'price' => '35.00', 'terms' => 'فلاش'],
            ['key' => 'steel-wool', 'name' => 'سلك مواعين', 'unit' => 'piece', 'price' => '5.00'],
            ['key' => 'dish-sponge', 'name' => 'إسفنجة مواعين', 'unit' => 'piece', 'price' => '5.00', 'terms' => 'سفنجة'],
            ['key' => 'trash-bags-roll', 'name' => 'أكياس قمامة (رول)', 'unit' => 'piece', 'price' => '20.00', 'terms' => 'اكياس زبالة'],
        ],
    ],

    'personal-care' => [
        'name' => 'عناية شخصية',
        'icon' => 'stars',
        'products' => [
            ['key' => 'soap-lux', 'name' => 'صابون لوكس', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'lux'],
            ['key' => 'soap-dove', 'name' => 'صابون دوف', 'unit' => 'piece', 'price' => '30.00', 'terms' => 'dove'],
            ['key' => 'shampoo-sunsilk-400ml', 'name' => 'شامبو صانسيلك 400 مل', 'unit' => 'piece', 'price' => '95.00', 'terms' => 'sunsilk شامبوه'],
            ['key' => 'shampoo-head-shoulders-200ml', 'name' => 'شامبو هيد آند شولدرز 200 مل', 'unit' => 'piece', 'price' => '85.00', 'terms' => 'head shoulders شامبوه'],
            ['key' => 'toothpaste-signal-100ml', 'name' => 'معجون سيجنال 100 مل', 'unit' => 'piece', 'price' => '40.00', 'terms' => 'signal معجون اسنان'],
            ['key' => 'toothbrush', 'name' => 'فرشاة أسنان', 'unit' => 'piece', 'price' => '25.00', 'terms' => 'فرشة'],
            ['key' => 'tissues-fine-box', 'name' => 'مناديل فاين (علبة)', 'unit' => 'piece', 'price' => '25.00', 'terms' => 'fine كلينكس'],
            ['key' => 'tissues-zeina-pocket', 'name' => 'مناديل جيب زينة', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'zeina'],
            ['key' => 'toilet-paper-6', 'name' => 'ورق تواليت 6 رول', 'unit' => 'piece', 'price' => '70.00', 'terms' => 'مناديل حمام'],
        ],
    ],

    'diapers-baby' => [
        'name' => 'حفاضات وأطفال',
        'icon' => 'heart',
        'products' => [
            ['key' => 'pampers-size-3', 'name' => 'حفاضات بامبرز مقاس 3', 'unit' => 'piece', 'price' => '320.00', 'terms' => 'pampers بمبرز بامبرس حفاض'],
            ['key' => 'pampers-size-4', 'name' => 'حفاضات بامبرز مقاس 4', 'unit' => 'piece', 'price' => '330.00', 'terms' => 'pampers بمبرز بامبرس حفاض'],
            ['key' => 'molfix-size-4', 'name' => 'حفاضات مولفيكس مقاس 4', 'unit' => 'piece', 'price' => '280.00', 'terms' => 'molfix حفاض'],
            ['key' => 'babyjoy-size-4', 'name' => 'حفاضات بيبي جوي مقاس 4', 'unit' => 'piece', 'price' => '220.00', 'terms' => 'babyjoy حفاض'],
            ['key' => 'babyjoy-single', 'name' => 'حفاضة بيبي جوي بالواحدة', 'unit' => 'piece', 'price' => '8.00', 'terms' => 'babyjoy حفاض'],
            ['key' => 'babyjoy-wipes', 'name' => 'مناديل مبللة بيبي جوي', 'unit' => 'piece', 'price' => '30.00', 'terms' => 'babyjoy wipes'],
            ['key' => 'baby-powder', 'name' => 'بودرة أطفال', 'unit' => 'piece', 'price' => '40.00'],
        ],
    ],

    'feed' => [
        'name' => 'أعلاف وعليقة',
        'icon' => 'tree',
        'products' => [
            ['key' => 'feed-poultry-starter-25kg', 'name' => 'علف بادي دواجن شكارة 25 كجم', 'unit' => 'piece', 'price' => '700.00', 'terms' => 'علف كتاكيت بادئ عليقة'],
            ['key' => 'feed-poultry-starter-loose', 'name' => 'علف بادي دواجن سائب', 'unit' => 'kg', 'price' => '29.00', 'terms' => 'علف كتاكيت بادئ عليقة'],
            ['key' => 'feed-poultry-grower-loose', 'name' => 'علف نامي دواجن سائب', 'unit' => 'kg', 'price' => '28.00', 'terms' => 'عليقة'],
            ['key' => 'feed-poultry-finisher-loose', 'name' => 'علف ناهي دواجن سائب', 'unit' => 'kg', 'price' => '27.00', 'terms' => 'عليقة'],
            ['key' => 'feed-cattle-16-50kg', 'name' => 'علف مواشي 16% شكارة 50 كجم', 'unit' => 'piece', 'price' => '950.00', 'terms' => 'عليقة مواشي'],
            ['key' => 'bran-fine', 'name' => 'ردة ناعمة', 'unit' => 'kg', 'price' => '14.00', 'terms' => 'نخالة'],
            ['key' => 'corn-yellow-cracked', 'name' => 'ذرة صفراء مجروشة', 'unit' => 'kg', 'price' => '18.00'],
            ['key' => 'feed-rabbits', 'name' => 'علف أرانب', 'unit' => 'kg', 'price' => '25.00', 'terms' => 'عليقة'],
            ['key' => 'feed-pigeons', 'name' => 'علف حمام (خلطة)', 'unit' => 'kg', 'price' => '35.00', 'terms' => 'عليقة'],
        ],
    ],

    'bread' => [
        'name' => 'خبز ومخبوزات',
        'icon' => 'basket2',
        'products' => [
            ['key' => 'bread-fino', 'name' => 'عيش فينو', 'unit' => 'piece', 'price' => '2.00', 'terms' => 'فينو خبز'],
            ['key' => 'bread-shami-5', 'name' => 'عيش شامي 5 أرغفة', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'خبز'],
            ['key' => 'toast-white', 'name' => 'توست أبيض', 'unit' => 'piece', 'price' => '30.00', 'terms' => 'عيش توست'],
            ['key' => 'rusk', 'name' => 'بقسماط', 'unit' => 'piece', 'price' => '25.00'],
            ['key' => 'croissant-molto', 'name' => 'كرواسون مولتو', 'unit' => 'piece', 'price' => '10.00', 'terms' => 'molto'],
        ],
    ],

    'misc' => [
        'name' => 'متفرقات',
        'icon' => 'three-dots',
        'products' => [
            ['key' => 'matches', 'name' => 'كبريت', 'unit' => 'piece', 'price' => '2.00'],
            ['key' => 'lighter', 'name' => 'ولاعة', 'unit' => 'piece', 'price' => '10.00'],
            ['key' => 'candles-pack', 'name' => 'شمع (باكو)', 'unit' => 'piece', 'price' => '15.00'],
            ['key' => 'plastic-bags-bundle', 'name' => 'أكياس بلاستيك (رابطة)', 'unit' => 'piece', 'price' => '15.00', 'terms' => 'شنط'],
            ['key' => 'batteries-aa-2', 'name' => 'بطاريات AA (اتنين)', 'unit' => 'piece', 'price' => '30.00', 'terms' => 'حجارة'],
            ['key' => 'aluminium-foil', 'name' => 'ورق ألومنيوم', 'unit' => 'piece', 'price' => '40.00', 'terms' => 'فويل'],
        ],
    ],

];
