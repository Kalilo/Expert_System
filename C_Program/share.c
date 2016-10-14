#include <stdlib.h>
#include <unistd.h>

void	rules(void);
char	done(void);
void	trues(void);
void	display(void);

char	a = -2;
char	b = -2;
char	c = -2;
char	d = -2;
char	e = -2;
char	f = -2;
char	g = -2;
char	h = -2;
char	i = -2;
char	j = -2;
char	k = -2;
char	l = -2;
char	m = -2;
char	n = -2;
char	o = -2;
char	p = -2;
char	q = -2;
char	r = -2;
char	s = -2;
char	t = -2;
char	u = -2;
char	v = -2;
char	w = -2;
char	x = -2;
char	y = -2;
char	z = -2;

void	error_quit(char e)
{
	if (e == 1)
		write(1, "Error: variable already set.\n", 30);
	else if (e == 2)
		write(1, "Error: contradicting statement.\n", 32);
	exit(1);
}

void	set_var(char *var, char val)
{
	if (*var != 0 && *var != 1)
		*var = val;
	else if ((*var == 0 && val) || (*var == 1 && !val))
		error_quit(1); 
}

void	set_or_case(char *var1, char *var2, char val1, char val2)
{
	if ((*var1 == 0 && val1) || (*var1 == 1 && !val1))
		set_var(var2, val2);
	else if ((*var2 == 0 && val2) || (*var2 == 1 && !val2))
		set_var(var1, val1);
	else if (((*var1 == 0 && val1) || (*var1 == 1 && !val1)) &&
			((*var2 == 0 && val2) || (*var2 == 1 && !val2)))
		error_quit(2);
}

void	set_and_case(char *var1, char *var2, char val1, char val2)
{
	set_var(var1, val1);
	set_var(var2, val2);
}

void	set_xor_case(char *var1, char *var2, char val1, char val2)
{
	if ((*var1 == 1 && val1) || (*var1 == 0 && !val1))
		set_var(var2, val2);
	else if ((*var2 == 1 && val2) || (*var2 == 0 && !val2))
		set_var(var1, val1);
	else if (((*var1 == 1 && val1) || (*var1 == 0 && !val1)) &&
			((*var2 == 0 && val2) || (*var2 == 1 && !val2)))
		error_quit(2);
	else if (((*var2 == 1 && val2) || (*var2 == 0 && !val2)) &&
			((*var1 == 0 && val1) || (*var1 == 1 && !val1)))
		error_quit(2);
}
