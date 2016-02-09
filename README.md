JMDRestBundle
=======
This bundle provide fast and simple way to generate REST api for your project entities without editing configs and creating any controllers.

### Feautures:
* CRUD web api
* Independent from other bundles and do not required bundles like FOSRestBundle or JMSSerializerBundle, etc..
* Built-in pagination and ordering

Installation
=======
* Download via composer
```bash
$ composer require jmd/rest-bundle dev-master
```
* Add into `app/AppKernel.php`:
```php
public function registerBundles()
    {
        $bundles = array(
			...
            new JMD\RestBundle\JMDRestBundle(),
			...
        );
	    ...

        return $bundles;
    }
```
* Add into `app/config/routing.yml`:
```yaml
jmd_rest:
    resource: "@JMDRestBundle/Controller/RestController.php"
    type:     annotation
    prefix:   /api
```

Usage
=======
Update, delete and add methods you can use as is after installation.

### Api routes:

#### Route parameters:
1. `bundleName` - name of entity bundle
2. `entityName` - name of entity
3. `id` - entity item id

|name|method|path|comment
|---|---|---|---|
|rest_get_entity_list|GET|/api/{bundleName}/{entityName}|Show list entity items|
|rest_get_entity_item|GET|/api/{bundleName}/{entityName}/{id}|Show entity item by id|
|rest_update_entity_item|PUT|/api/{bundleName}/{entityName}/{id}|Update entity item by id|
|rest_x_update_entity_item|PUT|/api/{bundleName}/{entityName}/{id}/x|Special update action for x-editable jQuery plugin|
|rest_delete_entity_item|DELETE |/api/{bundleName}/{entityName}/{id}|Delete entity item id|
|rest_entity_add_item|POST|/api/{bundleName}/{entityName} |Add new entity item|

### How to add or update item:

Request headers must have `Content-Type` equals `application/json`.
For update any field in entity we must construct there json structure:
```json
{
	"fieldName": "value",
	"fieldName2": "value"
}
```

Updating and posting supports relations. To save relations we have to set json like:
```json
{
	"relationFieldToMany": [id1,id2],
	"relationFieldToOne": id3
}
```

### How to show item or items:

For showing item in entity repository we must implement `\JMD\RestBundle\Entity\RestEntityInterface` and make methods:
* `findAllArray(array $order = [])` - must return query builder. Example:
```php
public function findAllArray(array $order = [])
{
		$qb = $this->createQueryBuilder('c');

		$qb->select('partial c.{id,name}');

		return $qb;
}
```
* `findOneArray($id)` - must return array or null result. Example:
```php
public function findOneArray($id)
{
		$qb = $this->createQueryBuilder('c');

		$qb
				->select('partial c.{id,name}')
				->where('c.id = :id')
				->setParameter('id', $id)
		;

		return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
}
```

After you make implementation, you can send GET request and will get result like this:
```json
# url: http://localhost/api/BundleName/Client
{
	"status": 200,
	"data": [
		{
			"id": 1,
			"name": "Test client"
		}
	]
}

# url: http://localhost/api/BundleName/Client/1
{
	"status": 200,
	"data":  {
		"id": 1,
		"name": "Test client"
	}
}
```

**What's all!**