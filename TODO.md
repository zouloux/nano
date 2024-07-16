# TODO

- Template error with NANO_DEBUG ( not found and syntax errors )
- Include EmailServer ( as EmailHelper ? ) with some examples ( template and layout )
- Twig cache for even faster responses
- Base is not working, declared routes with SimpleRouter does not have the base

# OLD OLD V2
# OLD OLD V2
# OLD OLD V2

## Nano V2

> Huge refacto, we split everything appart
> Controllers should be easier.
> Re-implement SimpleRouter ?

`Nano`
`Nano::init`
`Nano::start`
`Nano::renderer`
`Nano::loadResponders`
`Nano::loadControllers`
`Nano::getController`
`Nano::action`
`Nano::onError`

`Router`
`Router::add`
`Router::onNotFound`
`Router::getUrl`
`Router::getCurrentRoute`
`Router::getInputs` // Request:: ??
`Router::redirect` // Response:: ?
`Router::json` // Response:: ?
`Router::raw` // Response:: ?

`Cache::get`
`Cache::has`
`Cache::set`
`Cache::clear`
`Cache::define`
`Cache::setPrefix`

`Data::` -> this should be anything about data. Dot searching, get, has, set, merge, inject, etc ... 
			DynamicData / AppData / Assets should extend or compose this
			Check NanoUtils ;)

`DynamicData::path`
`DynamicData::readJSON5` ?
`DynamicData::read`
`DynamicData::write`

`AppData::get`
`AppData::load`
`AppData::inject`

`Envs::load`
`Envs::get`

`Session::get`
`Session::has`
`Session::set`
`Session::clear`

`Debug::` TODO

`Logger::` // AKA LogBuffer in todo/

`Files::listFolder`
`Files::recursiveSearchRoot`
`Files::recursiveRemoveDirectory`
`Files::copyFolder`

#### Changes in routes and controllers

Responders should be renamed routes. Routes can still be directories of php files.
Routes can now be an handler, or be connected to a Controller.
A controller should have an init, a processForm ( for post ) and a render methods.
We should be able to get route of a controller with custom params ( for action="" by example )
We need routes hooks !
Maybe a hook system in the whole app ?

#### Neat stuff

`Assets::` -> Manages script / styles / vite assets 
`Meta::` -> Manages all meta with overrides ( title / graphs )
Maybe move everything into a `Layout::` thing ?
It can manage more ( favicon / web app / robots / sitemap ... )

#### Other features

`Locale::` TODO

`Database::` TODO
`Model::` TODO
`ValueObject::` TODO
> ValueObject has _tableName ::createTable features
> ValueObject has auto serialize / unserialize of arrays feature like in Walter
> Should be flat / mysql / sqlite compatible