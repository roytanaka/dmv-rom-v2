// Illustrative usage snippets — import line plus a minimal example — shown beside
// each component specimen and copyable via the #77 clipboard composable. These
// are deliberately minimal and NOT an API contract: kept short on purpose to
// limit drift from the real component props (the page says so up top). Authored
// as plain strings so no syntax-highlighting dependency is needed.
export const snippets: Record<string, string> = {
    button: `import { Button } from '@/components/ui/button';

<Button variant="default" size="default">Sign up</Button>
<Button :loading="form.processing">Sign up</Button>`,

    input: `import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

<Label for="email">Email</Label>
<Input id="email" type="email" v-model="form.email" :aria-invalid="!!form.errors.email" />
<InputError :message="form.errors.email" />`,

    badge: `import { Badge } from '@/components/ui/badge';

<Badge variant="success" dot>Signed up</Badge>`,

    table: `import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

<Table>
  <TableHeader>
    <TableRow><TableHead>Tour</TableHead></TableRow>
  </TableHeader>
  <TableBody>
    <TableRow :data-state="row.selected ? 'selected' : undefined">
      <TableCell>Museum Highlights</TableCell>
    </TableRow>
  </TableBody>
</Table>`,

    card: `import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

<Card>
  <CardHeader>
    <CardTitle>Museum Highlights</CardTitle>
    <CardDescription>Wed 01 Jul · 11:00</CardDescription>
  </CardHeader>
  <CardContent>…</CardContent>
  <CardFooter><Button>Sign up</Button></CardFooter>
</Card>`,

    checkbox: `import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

<Checkbox id="notify" v-model:checked="notify" />
<Label for="notify">Email me when a tour changes</Label>`,

    dropdown: `import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

<DropdownMenu>
  <DropdownMenuTrigger as-child>
    <Button variant="outline">Options</Button>
  </DropdownMenuTrigger>
  <DropdownMenuContent align="start">
    <DropdownMenuItem>Edit details</DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>`,

    dialog: `import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

<Dialog>
  <DialogTrigger as-child>
    <Button>Cancel sign-up</Button>
  </DialogTrigger>
  <DialogContent>
    <DialogHeader>
      <DialogTitle>Cancel your sign-up?</DialogTitle>
      <DialogDescription>…</DialogDescription>
    </DialogHeader>
  </DialogContent>
</Dialog>`,

    tooltip: `import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

<TooltipProvider>
  <Tooltip>
    <TooltipTrigger as-child>
      <Button variant="outline">Hover</Button>
    </TooltipTrigger>
    <TooltipContent>Tours lock 24 hours before they start.</TooltipContent>
  </Tooltip>
</TooltipProvider>`,

    skeleton: `import { Skeleton } from '@/components/ui/skeleton';

<Skeleton class="h-4 w-3/4" />`,

    avatar: `import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

<Avatar size="base">
  <AvatarImage :src="volunteer.photo" alt="" />
  <AvatarFallback>{{ initials }}</AvatarFallback>
</Avatar>`,

    breadcrumb: `import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';

<Breadcrumb>
  <BreadcrumbList>
    <BreadcrumbItem><BreadcrumbLink href="#">Dashboard</BreadcrumbLink></BreadcrumbItem>
    <BreadcrumbSeparator />
    <BreadcrumbItem><BreadcrumbPage>Alex Rivera</BreadcrumbPage></BreadcrumbItem>
  </BreadcrumbList>
</Breadcrumb>`,

    collapsible: `import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

<Collapsible v-slot="{ open }" default-open>
  <CollapsibleTrigger>What should I bring?</CollapsibleTrigger>
  <CollapsibleContent>Wear your volunteer badge…</CollapsibleContent>
</Collapsible>`,

    navigationMenu: `import { NavigationMenu, NavigationMenuItem, NavigationMenuLink, NavigationMenuList } from '@/components/ui/navigation-menu';

<NavigationMenu>
  <NavigationMenuList>
    <NavigationMenuItem>
      <NavigationMenuLink href="#" :active="true">Tours</NavigationMenuLink>
    </NavigationMenuItem>
  </NavigationMenuList>
</NavigationMenu>`,

    separator: `import { Separator } from '@/components/ui/separator';

<Separator />
<Separator label="or" />
<Separator orientation="vertical" />`,

    sheet: `import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

<Sheet>
  <SheetTrigger as-child>
    <Button variant="outline">Filter tours</Button>
  </SheetTrigger>
  <SheetContent side="right">
    <SheetHeader>
      <SheetTitle>Filter tours</SheetTitle>
      <SheetDescription>…</SheetDescription>
    </SheetHeader>
  </SheetContent>
</Sheet>`,
};
