#include <stdlib.h>
#include <unistd.h>

void	rules(void);
char	done(void);
void	trues(void);
void	display(void);

char	a = -1;
char	b = -1;
char	c = -1;
char	d = -1;
char	e = -1;
char	f = -1;
char	g = -1;
char	h = -1;
char	i = -1;
char	j = -1;
char	k = -1;
char	l = -1;
char	m = -1;
char	n = -1;
char	o = -1;
char	p = -1;
char	q = -1;
char	r = -1;
char	s = -1;
char	t = -1;
char	u = -1;
char	v = -1;
char	w = -1;
char	x = -1;
char	y = -1;
char	z = -1;

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

int		main()
{
	int		count;

	count = 0;
	while (count < 100 && done())
	{
		rules();
		count++;
	}
	display();
}
char	done(void)
{
	if (a != 0 && a != 1)
		return (1);
	if (b != 0 && b != 1)
		return (1);
	if (g != 0 && g != 1)
		return (1);
	return (0);
}

void	trues(void)
{
	g = 2;
	v = 2;
	x = 2;
}

void	rules(void)
{
	if (c)
		set_var(&e, 1);
	if (a && b && c)
		set_var(&d, 1);
	if (a || b)
		set_var(&c, 1);
	if (a && !b)
		set_var(&f, 1);
	if (c || !g)
		set_var(&h, 1);
	if (v ^ w)
		set_var(&x, 1);
	if (a && b)
		set_and_case(&y, &z, 1, 1);
	if (c || d)
		set_or_case(&x, &v, 1, 1);
	if (e && f)
		set_var(&v, 0);
	if (a && b)
		set_var(&c, 1);
	if (c)
		set_and_case(&a, &b, 1, 1);
	if (a && b)
		set_var(&c, 0);
	if (!c)
		set_and_case(&a, &b, 1, 1);
}

void	display(void)
{
	if (g)
		write(1, "g is true.\n", 11);
	else
		write(1, "g is false.\n", 12);
	if (v)
		write(1, "v is true.\n", 11);
	else
		write(1, "v is false.\n", 12);
	if (x)
		write(1, "x is true.\n", 11);
	else
		write(1, "x is false.\n", 12);
}

char	done(void)
{
	if (a != 0 && a != 1)
		return (1);
	if (b != 0 && b != 1)
		return (1);
	if (g != 0 && g != 1)
		return (1);
	return (0);
}

void	trues(void)
{
	g = 2;
	v = 2;
	x = 2;
}

void	rules(void)
{
	if (c)
		set_var(&e, 1);
	if (a && b && c)
		set_var(&d, 1);
	if (a || b)
		set_var(&c, 1);
	if (a && !b)
		set_var(&f, 1);
	if (c || !g)
		set_var(&h, 1);
	if (v ^ w)
		set_var(&x, 1);
	if (a && b)
		set_and_case(&y, &z, 1, 1);
	if (c || d)
		set_or_case(&x, &v, 1, 1);
	if (e && f)
		set_var(&v, 0);
	if (a && b)
		set_var(&c, 1);
	if (c)
		set_and_case(&a, &b, 1, 1);
	if (a && b)
		set_var(&c, 0);
	if (!c)
		set_and_case(&a, &b, 1, 1);
}

void	display(void)
{
	if (g)
		write(1, "g is true.\n", 11);
	else
		write(1, "g is false.\n", 12);
	if (v)
		write(1, "v is true.\n", 11);
	else
		write(1, "v is false.\n", 12);
	if (x)
		write(1, "x is true.\n", 11);
	else
		write(1, "x is false.\n", 12);
}

