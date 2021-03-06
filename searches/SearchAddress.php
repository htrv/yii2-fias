<?php

namespace solbianca\fias\searches;

use solbianca\fias\models\FiasAddressObject;
use solbianca\fias\models\FiasHouse;
use yii\base\Model;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class SearchAddress extends Model
{
    /**
     * @var int
     */
    public $region;

    /**
     * @var string
     */
    public $street;

    /**
     * @var string
     */
    public $address_id;

    /**
     * @var string
     */
    public $house;

    /**
     * @var string
     */
    public $query;

    public $city_id;
    public $parent_id;
    public $street_id;

    /**
     * Поиск по улицам и улицам на дополнительных территориях
     *
     * @var array
     */
    protected $levels = [7, 91];

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['address_id', 'house', 'query', 'city_id', 'street_id'], 'string'],
            [['region'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * @return array
     */
    public function searchAddress()
    {
        $addresses = [];
        switch ($find = Yii::$app->request->post('find')){
            case 'city':
            case 'street':
                $dataProvider = $this->searchAddressObject(Yii::$app->request->post(), $find);
                $dataProvider->pagination->setPage(Yii::$app->request->post('page') -1);
                $addresses = ArrayHelper::toArray($dataProvider->getModels(), [
                    'solbianca\fias\models\FiasAddressObject' => [
                        'id' => 'address_id',
                        'value' => 'shortAddress',
                    ]
                ]);
                break;
            case 'house':
                $dataProvider = $this->searchHouse(Yii::$app->request->post());
                $dataProvider->pagination->setPage(Yii::$app->request->post('page')  -1);
                /** @var solbianca\fias\models\FiasHouse $addresses */
                $addresses = ArrayHelper::toArray($dataProvider->getModels(), [
                    'solbianca\fias\models\FiasHouse' => [
                        'id' => 'house_id',
                        'value' => 'fullNumber',
                    ]
                ]);
                break;

        }

        return [
            'data' => $addresses,
            'page' => $dataProvider->pagination->page + 1,
            'total' => $dataProvider->pagination->pageCount
        ];
    }

    /**
     * @param $params
     * @return ActiveDataProvider
     */
    protected function searchAddressObject($params, $find)
    {
        $query = FiasAddressObject::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        if (isset($params['region']) && ($params['region'] == 77 || $params['region'] == 78)) {
            if ($params['region'] == 77) {
                $params['city_id'] = '0c5b2444-70a0-4932-980c-b4dc0d3f02b5';
            }

            if ($params['region'] == 78) {
                $params['city_id'] = 'c2deb16a-0330-4f05-821f-1d09c93331e6';
            }
        }

        $this->load($params, '');

        if (!$this->validate()) {
            return $dataProvider;
        }

        if ($this->city_id) {
            $this->parent_id = $this->city_id;
        }
        if ($this->street_id) {
            $this->parent_id = $this->street_id;
        }

        if ($this->parent_id) {
            $query->andWhere(['fias_address_object.parent_id' => $this->parent_id]);
        }

        $query->andFilterWhere(['fias_address_object.region_code' => $this->region]);

        switch ($find) {
            case 'city':
                $query->andWhere(['fias_address_object.address_level' => [4, 6]]);
                break;
            case 'street':
                $query->andWhere(['fias_address_object.address_level' => [7, 91]]);
                break;
        }

        $query->andFilterWhere(['like', 'fias_address_object.title', $this->query]);

//        if ($this->parent_id) {
//            $query->join('left join', 'fias_address_object fa1', 'fa1.address_id = fias_address_object.parent_id');
//            $query->join('left join', 'fias_address_object fa2', 'fa2.address_id = fa1.parent_id');
//            $query->andWhere(['or',
//                ['fa1.address_id' => $this->parent_id],
//                ['fa2.address_id' => $this->parent_id],
//            ]);
//        }

        return $dataProvider;
    }

    /**
     * @param $params
     * @return ActiveDataProvider
     */
    protected function searchHouse($params)
    {
        $query = FiasHouse::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params, '');

        if (!$this->validate()) {
            return $dataProvider;
        }

        if ($this->city_id) {
            $this->parent_id = $this->city_id;
        }

        if ($this->street_id) {
            $this->parent_id = $this->street_id;
        }

        if ($this->parent_id) {
            $query->andWhere(['fias_house.address_id' => $this->parent_id]);
        } else {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        if ($this->query) {
            $query->andWhere(FiasHouse::tableName() . '.number like \'' .  intval($this->query) . '%\'');
        }

        $query->andWhere(['<>', 'TRIM(fias_house.number)', '']);

        $query->groupBy('house_id');

        return $dataProvider;
    }
}