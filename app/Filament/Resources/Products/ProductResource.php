<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required()
                    ->unique(),
                Textarea::make('verdict')
                    ->label('vatertagstour.de meint')
                    ->columnSpanFull(),
                Textarea::make('body')
                    ->helperText('HTML; shortcodes like [produkte] are kept from WordPress.')
                    ->rows(10)
                    ->columnSpanFull(),
                TextInput::make('rating')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(5)
                    ->step(0.5),
                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                TextInput::make('image')
                    ->helperText('Amazon image URL (hotlinked, never stored) or a path on the public disk.')
                    ->columnSpanFull(),
                Repeater::make('gallery')
                    ->helperText('Amazon image URLs only; Amazon forbids storing their images.')
                    ->schema([
                        TextInput::make('url')->url()->required(),
                        TextInput::make('alt'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Repeater::make('offers')
                    ->relationship()
                    ->orderColumn('position')
                    ->schema([
                        Select::make('portal')
                            ->options(['amazon' => 'Amazon', 'mydays' => 'mydays'])
                            ->required(),
                        TextInput::make('asin'),
                        TextInput::make('url')
                            ->label('Affiliate URL')
                            ->url()
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                TextInput::make('meta_description')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('is_published'),
                DateTimePicker::make('published_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                ImageColumn::make('image')
                    ->disk('public'),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_published')
                    ->boolean(),
                TextColumn::make('categories.name')
                    ->badge(),
                TextColumn::make('rating')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
