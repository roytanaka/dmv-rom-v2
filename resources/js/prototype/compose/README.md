# PROTOTYPE — compose placement and the Audience picker (#467)

Throwaway. Nothing here ships. Three structurally different answers to
"where does compose live, and what does the Audience picker look like?",
mounted inside the real Group, Schedule, Shift, Directory, and Member pages,
switched by `?variant=A|B|C` and the floating bar at the bottom of the screen.

Run: `pnpm dev` (host) with Sail up, sign in, then open any of:

- `/groups/<slug>?variant=A` (Group header), `/groups/<slug>/roster`, `/groups/<slug>/scheduling` and open a Schedule
- `/directory?variant=A`
- `/members/<id>?variant=A` (Direct message)

Left / right arrow keys cycle variants. The bar is hidden in a production build.

| Key | Name                            | Entry point                                                                                      | Picker                                                                                                                  | Composer                                     |
| --- | ------------------------------- | ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| A   | Header button, two-pane dialog  | One "Send email" button in each page header                                                      | Audience list on the left, ticked roster on the right, count in the footer. Unreachable Members are marked before send. | Same dialog, below the picker                |
| B   | Audience menu, stepped sheet    | A menu naming the Audiences at the entry point                                                   | Step 1 is the chosen Audience expanded into ticked rows; count in the step header                                       | Step 2 in a side sheet; step 3 is the result |
| C   | Select first, full-page compose | Tick boxes on the roster, sign-up chips, and Directory rows; a floating "N selected · Email" bar | Recipients are chips in a To field; Audiences are quick-add buttons                                                     | A full-page takeover, like a mail client     |

Sending is faked: every seventh Member is "unreachable" so the result line has something to say.
