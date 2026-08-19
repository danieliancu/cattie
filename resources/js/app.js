import './bootstrap';
import './embedded-checkout';
import Alpine from 'alpinejs';
import { ArrowRight, Backpack, ChevronDown, CloudUpload, createIcons, Gift, Heart, Image, LockKeyhole, Map, Menu, Paintbrush, PawPrint, RotateCcw, Search, ShieldCheck, ShoppingCart, Sparkles, Truck, UserRound, WandSparkles, X } from 'lucide';

window.Alpine = Alpine;
Alpine.start();

createIcons({
    icons: {ArrowRight, Backpack, ChevronDown, CloudUpload, Gift, Heart, Image, LockKeyhole, Map, Menu, Paintbrush, PawPrint, RotateCcw, Search, ShieldCheck, ShoppingCart, Sparkles, Truck, UserRound, WandSparkles, X},
});
